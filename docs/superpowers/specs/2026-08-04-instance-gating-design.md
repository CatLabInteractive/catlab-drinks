# Instance Gating, Registration Lockdown & First-Run Setup — Design

**Date:** 2026-08-04
**Status:** Approved for planning

## Problem

CatLab Drinks instances are open to unrestricted registration, and there is no way to
distinguish "this instance is someone's production system" from "this instance is a shared
playground". Practically:

- **drinks.catlab.eu** is used by De Quizfabriek in production, but is also open for other
  people to try the software. Those other organisations should see a warning that the shared
  instance is for testing only and that production use requires their own instance.
- **Freshly self-hosted instances** should not accept public registrations: only the founder
  should be able to create an account, unless the operator explicitly opens the server.
- **Setup instructions** must be clear enough that a warned user can realistically set up
  their own instance, including a friendly page when the app cannot reach its database
  (the most common first-deploy failure).

## Configuration

New config file `config/instance.php`:

| Config key | Env var | Default | Meaning |
|---|---|---|---|
| `production_organisation_ids` | `PRODUCTION_ORGANISATION_IDS` | `[]` (empty) | Comma-separated organisation IDs allowed to use this instance in production. Empty = private instance, **all** organisations are production. |
| `registration_open` | `REGISTRATION_OPEN` | `false` | When true, public registration stays open even after the first user exists. |

Parsing: comma-separated string → array of ints; whitespace tolerated; empty/unset → empty
array.

Values for drinks.catlab.eu: `REGISTRATION_OPEN=true`,
`PRODUCTION_ORGANISATION_IDS=<Quizfabriek organisation id>`.

## Organisation production flag

- `Organisation::isProductionAllowed(): bool` — returns true when
  `config('instance.production_organisation_ids')` is empty, otherwise
  `in_array($this->id, $list)`.
- The Management API `OrganisationResourceDefinition` gains a **read-only computed field
  `testMode`** (naming styled to match existing fields): `testMode = !isProductionAllowed()`.
- No new endpoint: the Manage app already fetches `users/me/organisations` at boot
  (`resources/manage/js/app.js` ~line 175), so the flag rides along for free.
- The Device (POS) API is intentionally **not** changed (warning is Manage-only, per design
  decision).

## Manage banner

- When the booted organisation has `testMode === true`, the Manage app layout shows a
  **persistent, non-dismissible** Bootstrap-Vue alert (warning variant) at the top:
  > "You're using this shared instance in testing mode — it's free to try things out, but
  > for production events please set up your own instance. [How to set up your own instance]"
- The link points to the "Run your own instance" section of the setup documentation
  (readme anchor; see Documentation).
- No state, no dismissal tracking.

## Registration lockdown & first-run setup

**Rule (applies everywhere):** registration is allowed ⇔ the `users` table is empty **or**
`config('instance.registration_open')` is true.

### First-run setup (`/setup`)

- While no users exist, `/login`, `/register` and the app entry points (`/manage`, home)
  redirect to `/setup`.
- `/setup` shows a single welcome form: **admin name, email, password (confirmed),
  organisation name**.
- On submit, inside a DB transaction that re-checks `User::count() === 0` (race-safe):
  1. Create the user (the `User` model event auto-creates an organisation named after the
     user).
  2. Rename that auto-created organisation to the submitted organisation name.
  3. Log the user in.
  4. Redirect to `/getting-started`.
- Once a user exists, `/setup` redirects to `/login`.

### After the first user

- `/register` (GET and POST) is blocked unless `registration_open` — the GET shows a
  friendly "registration is closed on this instance" page that links to the self-hosting
  docs; the POST returns a 403/redirect.
- "Register" links in blade views (login page, navbars, landing page) are hidden under the
  same condition.

### SSO path (CatLab Accounts)

When `services.catlab.client_id` is set, auth is delegated to the CatLab Accounts SSO
package and the local register routes are not used. The same registration rule must be
applied at the point where a **new local user** is auto-created on first SSO login: if users
exist and registration is closed, reject the new-user creation with the "registration
closed" page (existing users log in normally). If the package offers no clean hook for
this, scope it out and document that SSO instances delegate registration control to the SSO
server. (drinks.catlab.eu sets `REGISTRATION_OPEN=true`, so it is unaffected either way.)

## Database-error help page

- In `app/Exceptions/Handler.php`: for web (HTML) requests, when the exception is a
  connection-level database failure (e.g. `PDOException` / `QueryException` with
  connection error codes: host unreachable, access denied) **or** a "base table not found"
  error (migrations never ran), render a **static, DB-independent** blade page
  (`errors/database.blade.php`, styled like `/getting-started`).
- Page content: likely causes and fixes — check `DATABASE_URL` / `DB_*` env vars, verify
  the database server is running, run `php artisan migrate` — plus a link to the setup
  documentation.
- When `APP_DEBUG=true`, the normal debug error page is shown instead (developers keep
  full stack traces).
- The exception-classification logic ("is this a DB-connection/missing-migrations error?")
  is factored into a small testable class/method.

## Documentation

- **readme.md**: new **"Run your own instance"** walkthrough — pick a deploy target
  (Heroku / DigitalOcean / Dokku / Docker Compose / manual), deploy, land on the first-run
  setup screen, back up `APP_KEY`, optional topup domain & NFC setup. Includes a full
  **environment variable reference table** (APP_KEY, DATABASE_URL/DB_*, REGISTRATION_OPEN,
  PRODUCTION_ORGANISATION_IDS, TOPUP_DOMAIN_NAME, OAuth/Passport keys, mail, SSO client
  vars) with defaults and when each is needed.
- **Deploy manifests**: review `app.json` (Heroku) and the DigitalOcean template so a fresh
  one-click deploy lands correctly locked down. Notably they must **not** set
  `REGISTRATION_OPEN`.
- The Manage banner and the "registration closed" page both link to the "Run your own
  instance" docs.
- **CLAUDE.md / readme**: fix the outdated claim that no automated test suite exists;
  describe PHPUnit + Jest/Vitest + GitHub Actions CI.

## Automated testing

The repo already has the full base: PHPUnit 9 with `tests/Feature` + `tests/Unit`
(MySQL test DB `catlab_drinks_test`), Jest + Vitest for JS, and a GitHub Actions workflow
(`.github/workflows/tests.yml`) running PHPUnit on a PHP 8.1/8.2/8.3 matrix with a MySQL 8
service plus a frontend test job, triggered on push/PR to main/master/develop. New tests in
`tests/Feature` and `tests/Unit` therefore run in CI automatically — no workflow changes
expected.

New coverage:

- **`tests/Unit/InstanceConfigTest`** — comma-separated ID parsing: normal list, spaces,
  empty string, unset → empty array of ints.
- **`tests/Feature/SetupControllerTest`** — with empty users table: `/login`, `/register`,
  `/manage` redirect to `/setup`; submitting the form creates the user, renames the
  organisation, logs in, redirects to `/getting-started`; `/setup` redirects away once a
  user exists; double-submit race creates exactly one user.
- **`tests/Feature/RegistrationLockdownTest`** — with an existing user: GET `/register`
  shows the "closed" page, POST is rejected; with `config(['instance.registration_open' =>
  true])` both work; register links hidden/shown accordingly.
- **`tests/Feature/OrganisationTestModeTest`** — `isProductionAllowed()` for: empty config
  (true), listed ID (true), unlisted ID (false); `testMode` field value in the
  `users/me/organisations` API response for whitelisted and non-whitelisted organisations.
- **`tests/Feature/DatabaseErrorPageTest`** — the exception-classification unit logic, and
  the "table not found" branch rendering the help page.

Definition of done: all new tests pass locally against the MySQL test DB and the GitHub
Actions run is green.

## Out of scope

- POS/device-facing warnings (Manage-only banner).
- Admin UI for managing the whitelist (env var only).
- Invite/team-member flows.
- Per-device licensing (existing, unrelated).
