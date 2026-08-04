# Accounts profiles → organisations sync

**Date:** 2026-08-04
**Status:** Approved

## Background

The CatLab accounts SSO server was refactored around **profiles**: the owning entity for
teams, billing and memberships. Every accounts user has one *personal* profile
(auto-created) plus zero or more *shared* profiles. Membership is flat with a role field
(`10` = owner, `1` = member), and every profile carries a monotonic `sync_version`
counter bumped on every mirror-relevant write (rename, member add/remove, license
changes).

api.quizwitz.com already mirrors profiles into its local "publishers" via a
`ProfileMirror` (see `api.quizwitz.com/src/QuizWitz/Profiles/ProfileMirror.php` and the
specs in `api.quizwitz.com/docs/superpowers/specs/`). This spec ports that pattern to
catlab-drinks, where profiles map to **organisations**.

Authoritative wire shapes (accounts repo,
`docs/superpowers/specs/2026-07-24-publishers-to-profiles-and-auth-unification-design.md`):

- `GET /api/1.0/profiles` (bearer) →
  `{"items":[{"id":123,"name":"Team X","role":10,"personal":false,"version":7},…]}`
  (ordered by profile id; guarantees the personal profile exists; a `debug` key is
  appended to every response — ignore it).
- `GET /api/1.0/profiles/{id}/members` (bearer, membership-checked) →
  `{"items":[{"userId":42,"role":10},…]}` — `userId` is the accounts user id, i.e. what
  drinks stores as `users.catlab_id`.
- Accounts → client delegated manage calls: `POST` to the client's `manage_user_uri`
  with body `{user_id | profile_id, action, …}` and header
  `Authorization: Bearer: <client_secret>` (note the literal colon after `Bearer`).
- No push webhooks for profile changes: sync is pull-based.

Current drinks state:

- SSO via `catlabinteractive/laravel-catlab-accounts` (Socialite driver `catlab`);
  users are matched on `users.catlab_id`, and `users.catlab_access_token` is refreshed
  on every login (accounts tokens live 2 years).
- The only SSO seam is `App\Http\Controllers\Auth\SsoLoginController::getUserFromSocialite()`.
- `User::boot()` has a `created` hook that auto-creates one organisation per user.
- `organisation_user` is many-to-many (no role column), but the app assumes one org
  (`->organisations()->first()` in `ClientController`, `LicenseController`,
  `SetupController`).
- The Manage SPA boots by setting `window.ORGANISATION_ID` from the first item of
  `GET /api/v1/users/me` `organisations`; ~12 views read that global.
- Organisations have no external id column.

## Decisions

| Decision | Choice |
|---|---|
| Sync trigger | SSO login + throttled (15 min) opportunistic re-sync on authenticated requests using the stored `catlab_access_token` |
| Multi-org | Full membership mirroring **and** an organisation switcher in Manage |
| Renaming linked orgs | Read-only in drinks; deep-link to accounts to rename |
| Delegated manage endpoint | Minimal implementation (user `delete`/`logout`) |
| Licenses/credits sync | Out of scope — drinks has none |
| Accounts-token introspection for drinks APIs | Out of scope — Passport/device auth stays |
| Role mirroring | Not mirrored — drinks membership stays flat |
| Org reaper | None — orgs whose profile disappears simply stop updating |

## 1. Schema

One migration:

- `organisations.profile_id` — `unsignedBigInteger`, nullable, **unique**. The accounts
  profile link; the unique key is the backstop for the mirror's link races.
- `organisations.profile_sync_version` — `unsignedBigInteger`, nullable. Last
  fully-applied accounts `sync_version`; `NULL` means "never fully synced under the
  version protocol" and forces one full pass.
- `users.last_profile_sync` — nullable `timestamp`. Throttle/backoff marker.

No backfill. Existing organisations get linked through the personal-profile adoption
rule at each user's next login/sync.

## 2. `ProfileMirror` service

`app/Services/ProfileMirror.php`. Uses Laravel's `Http` client (timeout 5 s, connect
timeout 2 s). Endpoints are built from `config('services.catlab.url')`:
`api/1.0/profiles` and `api/1.0/profiles/{id}/members`.

### `sync(User $user, bool $force = false)`

1. **Kill switch:** `config('services.catlab.disable_profile_mirror')`
   (`DISABLE_PROFILE_MIRROR` env). Blocks even forced syncs. Ops can freeze the mirror
   without redeploying.
2. **Token:** return early when `catlab_access_token` is empty.
3. **Throttle (15 min):**
   - Cheap pre-check on the already-loaded `$user->last_profile_sync` attribute — in
     steady state an authenticated request costs zero extra queries.
   - Unforced: claim atomically with one conditional update — only the winner proceeds:
     `UPDATE users SET last_profile_sync = NOW() WHERE id = ? AND (last_profile_sync IS NULL OR last_profile_sync < NOW() - INTERVAL 900 SECOND)`
   - Forced: stamp unconditionally and always proceed.
4. **Fetch profiles list** with the user's bearer. Transport error, non-200 or
   malformed body → log, **stamp failure backoff** (set `last_profile_sync` to
   `now − (900 − 60)` so retries reopen after 60 s instead of hammering accounts during
   an outage), return.
5. **Per profile item** → `syncProfile()` (below). A `false` return aborts the whole
   remaining sync + failure backoff; profiles already processed keep their updates.
6. Stamp `last_profile_sync = now`.

Never let the mirror throw out of `sync()`: callers wrap it anyway, but the mirror
itself catches transport-level problems and converts them to backoff.

### `syncProfile(User $user, array $item, bool $force): bool`

Item: `{id, name, role, personal, version}`. `version` may be absent (older accounts) →
always full-sync that profile.

1. **Resolve the organisation:**
   - `Organisation::where('profile_id', $item['id'])` first — never create a duplicate.
   - Else, if `personal`: **adopt** the oldest unlinked organisation the user is the only
     member of (`$user->organisations()->whereNull('profile_id')->has('users', '=', 1)->orderBy('organisations.id')->first()`)
     and link it. This is how every pre-existing auto-created org gets linked. Orgs shared
     with other members are skipped — adopting one would hijack an organisation other
     people are relying on — so a user whose unlinked orgs are all shared falls through
     to the create-a-new-organisation path below. On a unique-key violation
     (`QueryException`, MySQL error 1062 — concurrent request won the link race):
     re-fetch the winner by `profile_id`; rethrow anything else.
   - Else: create a new organisation (name from the item), attach the user, link it.
     On 1062: delete the just-created husk (org + its membership rows — no content
     exists yet) and adopt the winner.
   - Lost both races: return `false` (retry later via backoff).
2. **Name is canonical on accounts:** reconcile `name` from the list item on every
   pass, deliberately *outside* the skip guard (defense-in-depth; costs nothing).
3. **Skip guard (incremental sync):** skip the roster fetch entirely when
   `!$force && !$created && $version !== null && $org->profile_sync_version === $version && <user is locally a member>`.
   The membership term is critical: a member added on accounts before their own first
   drinks login has no local user row, so `replaceMembers` silently dropped them; when
   they do log in, the version alone would match and they'd be skipped forever. The
   membership check runs last (it is the only DB query in the guard).
4. **Roster:** `GET /profiles/{id}/members`. Failure → return `false` — **never wipe
   membership on a failed fetch**. Map each `userId` to a local user via
   `users.catlab_id`; unknown members are skipped (they're picked up at their own first
   login, which the skip-guard membership term guarantees). Roles are not mirrored.
5. **Apply + stamp, serialized per organisation:** inside `DB::transaction()`, re-fetch
   the organisation row with `lockForUpdate()`, then replace memberships
   (`$org->users()->sync($localUserIds)`) and store `profile_sync_version = $version`.
   Serialization makes "last to apply" == "last to stamp": a stale applier also stamps
   its stale version, so the next sync sees a mismatch and self-heals. Both remote
   fetches stay outside the transaction. A `$version` of `null` deliberately
   overwrites, keeping stored == what accounts last said.

### What stays from today

- The `User::created` auto-org hook **stays**: it guarantees a usable org even when
  accounts is unreachable during first login, and the adoption rule links/renames it on
  the first successful sync. Non-SSO instances are unaffected by this whole feature.
- `isMyOrganisation()` reads the cached `$user->organisations` relation; the mirror
  unsets the loaded relation after membership changes so same-request authorization
  sees fresh data.

## 3. Triggers

1. **SSO login** — `SsoLoginController::getUserFromSocialite()`: after
   `parent::getUserFromSocialite()` returns the saved user, call
   `ProfileMirror::sync($user)` (throttled, not forced) wrapped in `try/catch
   (\Throwable)` — a mirror failure must never fail login.
2. **`SyncAccountsProfiles` middleware**, appended to both the `web` and `api`
   middleware groups: no-op unless the authenticated principal is a `User` with a
   `catlab_access_token`; otherwise throttled `sync()`, same try/catch. This is what
   makes accounts-side membership changes appear without re-login. Device API requests
   authenticate a `Device` principal and fall through untouched.

No scheduler, no webhook — matches quizwitz.

## 4. Organisation switcher (Manage)

- **Boot** (`resources/manage/js/app.js`): extract an `selectOrganisation(items)`
  helper — return the org whose id matches
  `localStorage['catlab_drinks_manage_organisation_id']` when present in the list,
  else the first item. Set `window.ORGANISATION_ID` / `window.ORGANISATION_TEST_MODE`
  from it. All existing `window.ORGANISATION_ID` consumers stay untouched.
- **Navbar** (`resources/manage/js/views/App.vue`): a `b-nav-item-dropdown` labelled
  with the current organisation name, rendered only when the user has more than one
  organisation. Selecting an org writes the localStorage key and reloads the page
  (everything reads the global at boot, so a reload is the correct, simple invalidation).
- **Backend single-org call sites:**
  - `ClientController::manage()`: the blade only inlines
    `var ORGANISATION_ID = {{ $organisation->id }}` as a pre-boot placeholder that
    `app.js` overwrites during boot — keep passing `first()` (guarding against a user
    with no organisations) and let the frontend selection stay authoritative.
  - `LicenseController::applyLicense()`: uses `first()` only to scope the device
    lookup. Replace with "device belongs to *any* of the user's organisations"
    (`whereIn('organisation_id', $user->organisations()->pluck('organisations.id'))`),
    which is the correct check under multi-org.
  - `SetupController` only runs on non-SSO instances — unchanged.

POS is untouched: devices are already bound to one organisation.

## 5. Read-only name for linked organisations

- **Backend:** in the Charon `OrganisationController`, override `beforeSaveEntity`
  (aliasing the trait method) and reject the save with a validation error when
  `$entity->isDirty('name') && $entity->profile_id !== null`, message pointing to
  accounts ("This organisation's name is managed on CatLab Accounts").
- **Frontend:** where the organisation name is shown/edited in Manage, render it
  read-only for linked orgs with a "Rename on CatLab Accounts" link to
  `{accounts_url}/profiles/{profile_id}`.
- Expose `profile_id` (read-only) on the organisation resource definition so the
  frontend can tell linked from unlinked.

## 6. Delegated manage endpoint

- **Route:** `POST /delegated/users` (plain Laravel route, CSRF-exempt, outside both
  Charon APIs).
- **Auth middleware:** compare the `Authorization` header against
  `'Bearer: ' . config('services.catlab.client_secret')` using `hash_equals`; `400`
  on mismatch or when no client secret is configured (mirrors quizwitz).
- **`user_id` actions** (user resolved via `users.catlab_id`; unknown user → 404,
  which accounts interprets as "already removed"):
  - `delete`: detach organisation memberships, revoke Passport tokens, delete the user
    row. Organisations and their data remain.
  - `logout`: revoke Passport tokens.
  - anything else (`info`, `activity`, …): `200` no-op with an empty JSON object — an
    unsupported action must never break an accounts flow.
- **`profile_id` actions:** none supported → `400` with
  `Action %s is not supported for profile_id` (mirrors quizwitz).

## 7. Error handling summary

| Failure | Behaviour |
|---|---|
| Accounts down / non-200 on profiles list | Log, failure backoff (60 s), auth continues |
| Roster fetch fails for one profile | That profile keeps its current membership; whole sync aborts with backoff |
| Link race (duplicate `profile_id`) | Loser adopts the winner (deleting its husk if it created one) |
| Mirror throws unexpectedly | Caught at every trigger; never fails login or a request |
| `DISABLE_PROFILE_MIRROR=1` | Mirror is a hard no-op, forced or not |
| Token revoked on accounts (401) | Treated as fetch failure → backoff; token is not cleared |

## 8. Testing

- **`tests/Feature/ProfileMirrorTest.php`** (`RefreshDatabase`, `Http::fake`), porting
  the behaviours pinned by quizwitz's `ProfileMirrorTest`:
  create / adopt / link-by-profile-id; adoption renames; race-loser adoption (simulate
  by pre-linking the winner); husk cleanup; unknown members skipped; membership
  replacement incl. removal; failed roster leaves membership intact; skip-guard matrix
  (version match skips, force overrides, `created` overrides, non-member overrides,
  missing version full-syncs); throttle claim (second sync inside 15 min is a no-op);
  failure backoff window; kill switch; version stamped only after successful apply.
- **SSO login sync:** extend the `SsoRegistrationGateTest` Socialite-mocking pattern —
  login triggers a sync; a mirror failure still logs the user in.
- **Middleware:** authenticated web/api request triggers a throttled sync; guests and
  device principals don't.
- **Delegated endpoint:** wrong/missing secret rejected; `delete` removes user +
  memberships and revokes tokens but keeps organisations; `logout` revokes tokens;
  unknown user → 404; unsupported action → 200 no-op; `profile_id` action → 400.
- **Read-only name:** renaming a linked org via the API fails with a validation error;
  unlinked org rename still works.
- **Frontend (vitest):** the `selectOrganisation` boot helper (stored id wins when
  present, falls back to first, ignores stale stored ids).

## 9. Config / env

```
# .env.example additions (next to the existing CATLAB_* block)
DISABLE_PROFILE_MIRROR=        # ops kill switch for the profiles → organisations sync
```

`config/services.php` `catlab` block gains `disable_profile_mirror`. Endpoint URLs are
derived from the existing `services.catlab.url`; no new endpoint config.

## 10. Rollout notes

- Deploy is safe in any order: the migration is additive; unsynced users keep their
  current single org until their next login/request.
- Accounts side needs the drinks OAuth client's `manage_user_uri` set to
  `https://<drinks>/delegated/users` for §6 to receive calls (optional otherwise).
- `DISABLE_PROFILE_MIRROR=1` is the emergency stop; while it is set, logins behave
  exactly as before this feature.
