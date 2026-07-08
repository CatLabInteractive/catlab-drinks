# Device Capability Flags: Sales / Topup

**Date:** 2026-07-08
**Status:** Approved

## Problem

Every paired POS device can top up, reset, and rebuild NFC cards via the Cards
page. At festivals, organisers want dedicated topup stations staffed by trusted
people, while bar devices should only sell. Bar volunteers must not be able to
top up or reset cards — and must not be able to grant themselves that ability.

## Solution overview

Two new admin-controlled boolean capability flags on the `Device` model:

| Flag | Default | Gates |
|------|---------|-------|
| `allow_sales` | `true` | Order processing: events UI, live + remote orders |
| `allow_topup` | `true` | The Cards page: topup, reset, rebuild, discount, aliases |

Both flags are editable **only** through the Management API. The Device API
exposes them read-only, so a device cannot change its own capabilities. The POS
hides the corresponding UI. Because card writes happen offline with the
device's signing key, the server cannot physically prevent a rogue topup;
instead it **flags** unauthorized topup/reset/refund transactions at upload
time so admins can detect misuse.

## 1. Data model

Migration adds to `devices`:

- `allow_sales` — boolean, default `true`
- `allow_topup` — boolean, default `true`

Defaults keep existing devices fully functional after upgrade.

Migration adds to `card_transactions`:

- `uploaded_by_device_id` — nullable unsigned FK to `devices.id`, set on every
  transaction uploaded through the Device API (general audit value)
- `unauthorized` — boolean, default `false`

`Device` model: add both flags to `$fillable` and `$casts`. The existing
`static::updated` reassignment hook also triggers when `allow_sales` is
switched off (same treatment as `allow_remote_orders` being disabled).

`Transaction` model: add `unauthorized` cast; add `uploadedByDevice()`
relationship.

## 2. API exposure (security boundary)

- `App\Http\ManagementApi\V1\ResourceDefinitions\DeviceResourceDefinition`:
  both fields `->bool()->visible(true)->writeable(true, true)`.
- `App\Http\DeviceApi\V1\ResourceDefinitions\DeviceResourceDefinition`:
  both fields `->bool()->visible(true)->writeable(false)`.

Charon does not apply non-writeable fields on input, so
`PUT /pos-api/v1/devices/current` silently ignores attempts to set them. No
policy changes required — `DevicePolicy` self-edit stays as is.

## 3. Manage UI

`resources/manage/js/views/Devices.vue`:

- Edit-device modal (currently name only) gains two checkboxes:
  "Allow sales" and "Allow card topups". `saveEdit()` sends both fields.
- Device list shows compact badges per device (e.g. "sales", "topup") so the
  role split is visible at a glance.

## 4. POS behavior

The POS already fetches and offline-caches `GET /pos-api/v1/devices/current`
at boot (`resources/pos/js/app.js`). Expose:

- `window.DEVICE_ALLOW_SALES` (default `true` when absent)
- `window.DEVICE_ALLOW_TOPUP` (default `true` when absent)

Gating (all in the POS app only; Manage is unaffected):

- **`allow_topup = false`:**
  - "Cards" navbar item hidden (`resources/pos/js/views/App.vue`)
  - `/cards` and `/transactions` routes redirect to the default landing page
- **`allow_sales = false`:**
  - "Events" navbar item hidden
  - Event routes (`/events`, `/events/:id/*`) redirect to the default landing
    page
  - Live/remote order toggles hidden in POS Settings
    (`resources/pos/js/views/Settings.vue`)
  - Default landing route becomes `/cards` instead of the events list
- **Both `false`:** an explanatory screen ("This device has no functions
  enabled. Contact your administrator.") — reachable settings page stays
  available for sync/logout.

Route gating is implemented with a router `beforeEach` guard (or per-route
`beforeEnter`) reading the window flags, so deep links are covered, not just
nav visibility.

Server side, `App\Services\OrderAssignmentService` excludes devices with
`allow_sales = false` from remote-order assignment (both in the eligibility
check and the assignment query), mirroring the `allow_remote_orders`
handling.

## 5. Unauthorized-transaction audit flag

In the Device API transaction upload path
(`App\Http\Shared\V1\Controllers\TransactionController::beforeSaveEntity`,
device context):

1. Always set `uploaded_by_device_id` to the authenticated device's id when
   the principal is a `Device`.
2. If `transaction_type` ∈ {`topup`, `reset`, `refund`} **and** the uploading
   device has `allow_topup = false`, set `unauthorized = true`. The
   transaction is still saved (rejecting it would desync the books from the
   card, which already carries the balance).

Exposure:

- `TransactionResourceDefinition` (Management API): `unauthorized` visible,
  not writeable.
- Manage Transactions overview (`TransactionsTable.vue` /
  `Transactions.vue`): red "unauthorized" badge on flagged rows.

Out of scope: notifications/alerts on flagged transactions; admins review the
transactions overview.

## 6. Testing

- No PHP test suite exists; backend verified manually: run migration, check
  `php artisan route:list`, verify Management API accepts the fields and
  Device API ignores writes to them, verify a topup transaction uploaded by a
  restricted device gets flagged.
- Frontend: Vitest tests for the POS route guard / nav gating, following the
  existing route/view test pattern in `resources/tests/`.
- Jest NFC tests unaffected (no changes to card format or signing).

## Known limitations

- A rogue device can inflate a card's balance offline and never upload a
  typed transaction for it; the delta only later syncs as an unflagged
  `unknown`/overflow transaction via the card-data path. Attribution exists
  via the card's `last_signing_device_id`, but no `unauthorized` badge is
  raised for this path.
- A fabricated positive `reversal` upload from a sales-only device is not
  flagged, since legitimate reversals are positive on such devices; it still
  carries `uploaded_by_device_id` so it can be reviewed manually.
- The `unauthorized` flag is detection, not prevention: offline card writes
  cannot be rejected server-side, because the card already carries the
  balance and the server only sees the upload after the fact.

## Out of scope

- No changes to the NFC key approval system: a sales-only device keeps its
  approved signing key because deducting balances during sales requires it.
- No mode enum / migration of `allow_live_orders` / `allow_remote_orders`;
  those flags remain device-editable and govern *how* a sales-enabled device
  sells.
- No PIN-override mechanism to temporarily unlock topup on a device.
