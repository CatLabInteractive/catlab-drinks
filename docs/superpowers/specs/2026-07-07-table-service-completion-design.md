# Table Service Completion & Hardening — Design

**Date:** 2026-07-07
**Branch:** `copilot/feature-table-service-integration`
**Status:** Approved by Thijs (2026-07-07)

## Context

The table service branch already contains the data model (`tables`, `patrons`, order
columns), CRUD APIs on both API layers with policies, the POS `TableService.vue`
component, the waiter dashboard, the Pay Later flow, and tests. This design covers the
remaining work to make the feature complete and safe to merge:

1. Server-side order integrity (security fixes — merge blocking)
2. Wiring `PatronAssignmentService` into order intake (table QR + named orders)
3. Configurable bar prepared/delivered loop + atomic settlement
4. NFC hardening (scoped to dependency audit + revocation re-check)
5. Structure cleanups that ride along with the above

Explicitly **out of scope** (considered and deferred): removing the org-wide v0 HMAC
secret from device payloads, server-side card rollback detection, and a P-256 card
format. `min_nfc_version` per organisation already exists and remains the v0 sunset
lever.

---

## 1. Server-side order integrity

### Why model-level

`PublicController@order` calls `$entity->saveRecursively()` directly and never passes
through the Charon CRUD controller hooks. Controller-level `beforeSaveEntity` checks
would therefore not protect the public (unauthenticated) endpoint. Validation lives in
Eloquent model events, which fire on every write path. This also matches the CLAUDE.md
guidance to prefer model events for logic that must trigger regardless of endpoint.

### Rules

**`Order` `saving` event:**

- If `patron_id` is set or changed: the patron must exist and
  `patron->event_id === order->event_id`, otherwise abort with a validation error
  (HTTP 422 at the API boundary).
- If `table_id` is set or changed: same check against `table->event_id`.
- `payment_status` must be one of `unpaid`, `paid`, `voided`. Also enum-validated in
  `OrderResourceDefinition`.
- Setting `payment_status` to `unpaid` (on create, or transitioning from `paid`)
  requires `event->allow_unpaid_table_orders` to be true. Transitions `unpaid → paid`
  and `unpaid|paid → voided` are always allowed.

**`Patron` `saving` event:**

- If `table_id` is set or changed: the table must belong to the same event.

**`paid` bool ↔ `payment_status` sync (in the same `saving` event):**

- `payment_status` is the canonical field.
- When `payment_status` changes to `paid` → `paid = true`.
- When `payment_status` changes to `unpaid` or `voided` → `paid = false`.
- Existing code paths that only set `paid` keep working; the sync rule only fires on
  `payment_status` changes, so legacy flows are untouched.

**Public order endpoint (`PublicController@order`):**

- `patron_id`, `table_id`, and `payment_status` are stripped from client input
  (forced server-side after `toEntity`, the same way `paid` and `uid` already are).
- `payment_status` is derived from the actual outcome: card charged → `paid`;
  order accepted unpaid (event allows it) → `unpaid`.
- Patron/table assignment happens only via `PatronAssignmentService` (section 2).

### Tests

PHPUnit feature tests proving: cross-event `patron_id`/`table_id` injection returns
422 on the public endpoint, the device API, and the management API; invalid
`payment_status` strings are rejected; `unpaid` is rejected when the event setting is
off; the paid/payment_status sync rule.

---

## 2. Wire patron assignment (table QR + named orders)

### URL / parameter flow

- `table` becomes a recognized query parameter on remote order URLs
  (`/order/{token}?table=12`).
- `table` joins the **signable** parameter set from `.ai/signed-order-urls.md`, but
  unlike `card`/`name` it is also accepted **unsigned**:
  - Signature enforcement rule stays: signature required iff `card` or `name` is
    present (for events with an `order_token_secret`).
  - When a signature is present, it must cover `table` too (alphabetical sort:
    `card`, `name`, `table`).
  - A bare `?table=12` with no signature is accepted. Spoofing a table number is
    equivalent to telling a waiter the wrong table — accepted risk.
- The client app passes the table via an `X-Table-Number` header, mirroring
  `X-Card-Token`/`X-Order-Name`. `PublicEventApiAuthentication` includes it in
  signature validation when a signature is present.
- `OrderController@view` (web) passes the validated table number to the Vue client
  app the same way `card`/`name` are passed today.

### Backend integration

In `PublicController@order`, after the order entities are built (and after the
split-by-categories step), resolve the patron once and associate it with every
resulting order:

- Name present (`X-Order-Name`) → `PatronAssignmentService` reuses a patron with that
  name having orders in the last 24h, or creates one (quiz-app flow).
- Table present (no name) → reuse the table's last patron if they still have unpaid
  orders, else create a new patron for the table (`findOrCreateTable` auto-creates
  unknown table numbers) — the table-QR flow.
- Neither → no patron assignment (unchanged behaviour).

This makes the currently-dead `PatronAssignmentService` live; its existing unit tests
already cover the algorithm.

### Tests

Feature tests for: named order creates/reuses patron; anonymous table order reuses
open-tab patron and rotates to a new patron when the tab is settled; unknown table
number auto-creates the table; signature validation covering `table`.

---

## 3. Bar prepared/delivered loop + atomic settlement

### Event setting

New boolean event setting `bar_prepares_table_orders`, default **false** (current
"waiter does everything" behaviour — existing users see no change). Editable in the
Manage event modal next to `allow_unpaid_table_orders`.

### Status flow

Two vocabularies are reconciled without data migration:

- **Non-table orders:** `pending → processed | declined` (unchanged).
- **Table orders with the setting ON:** waiter-created table orders enter the bar
  queue as `pending`; the bar's RemoteOrders view marks them `prepared` (its "done"
  action maps to `prepared` for orders with a `table_id`); the waiter dashboard sees
  the flip and offers only "mark delivered".
- **Table orders with the setting OFF:** orders skip the bar queue; the waiter keeps
  the current mark-prepared / mark-delivered buttons.

### Atomic settlement

New endpoint on both APIs: `POST /patrons/{id}/settle`.

- **Body:** `{ payment_type: string, discount?: number }`.
- **Behaviour:** in one DB transaction, select the patron's `unpaid` orders (locked),
  set `payment_status = 'paid'` (the model sync rule sets `paid = true`) and record
  `payment_type`; return the updated orders and the settled total.
- **Idempotent:** settling a patron with no unpaid orders returns an empty result and
  200 — the client can retry safely after a network failure.
- **Authorization:** same policy check as patron edit (`PatronPolicy::edit`, devices
  allowed).
- **Client flow:** compute total → run PaymentPopup for the summed amount → on
  success make one settle call. This replaces the current `Promise.all` of N
  individual order PUTs, which could half-settle a tab after the card was already
  charged. The NFC discount returned by the payment flow is passed to the endpoint
  and recorded.

### Tests

Feature tests: settle marks only that patron's unpaid orders, is idempotent, respects
policies. Frontend: service-level Vitest tests for the settle call; manual POS
walkthrough of both setting modes.

---

## 4. NFC hardening (dependency audit scope)

- Pin/upgrade the `elliptic` npm package to ≥ 6.6.1 (fixes known
  signature-verification CVEs, e.g. CVE-2024-48949) and run `npm audit` over the
  NFC-related dependencies. Per project rules: edit `package.json` explicitly, never
  `npm update`, and revert `package-lock.json` changes unless `package.json` itself
  changed.
- Re-check device key approval during `CardService.refreshCard` (when the API is
  reachable) instead of only at boot, so a revoked device stops signing at the next
  card scan rather than at the next reload. On revocation: set approval status to
  `revoked`, which already blocks card operations via `isCardOperationAllowed()`.

Documented accepted risks (no code change): P-192 (~96-bit) signatures as an NTAG213
size trade-off; v0 HMAC path until organisations raise `min_nfc_version`; CryptoJS
passphrase-mode AES for private key storage.

---

## 5. Structure cleanups (riding along)

- Extract the duplicated order-queue/patron logic shared by
  `resources/pos/js/components/TableService.vue` (491 lines) and
  `resources/shared/js/views/WaiterDashboard.vue` (313 lines) into shared components
  under `resources/shared/js/components/` (order queue table + patron detail panel),
  and move settle/status-transition calls into `OrderService`/`PatronService` — the
  natural landing spot for the new settle endpoint and the section-3 status logic.
- Add `resources/shared/js/orderStatus.js` exporting the status and payment-status
  constants; replace the scattered string literals in the five affected
  views/components. Values must match the `Order` model constants.
- Fix `app/Http/ManagementApi/V1/routes.php:99` to register
  `\App\Http\ManagementApi\V1\Controllers\OrderController` (the thin stub) instead of
  the Shared class directly, per the documented pattern.
- Docs: update CLAUDE.md (a PHPUnit + Vitest suite now exists), `.ai/table-service.md`
  (patron assignment now actually wired; settlement endpoint; bar-loop setting), and
  `.ai/signed-order-urls.md` (`table` parameter).
- Add missing i18n keys for the new views to `nl`, `fr`, `de`, `es`.

---

## Implementation order

1. Section 1 (integrity rules) — everything else builds on validated writes.
2. Section 2 (patron assignment) — depends on 1 for safe patron/table association.
3. Section 3 (bar loop + settlement) — touches the same frontend files as section 5,
   so the component extraction happens at the start of this step.
4. Section 4 (NFC) — independent, can go anywhere.
5. Docs/i18n — last.
