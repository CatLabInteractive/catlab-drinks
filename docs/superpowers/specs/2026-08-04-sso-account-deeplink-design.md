# SSO account deeplink in the Manage menu

**Date:** 2026-08-04
**Status:** Approved

## Background

SSO users manage their credentials (password, email, billing) on the CatLab accounts
server, not in drinks. The accounts server exposes a user-facing `/myaccount` page and
supports one-shot login via an `authcode` query parameter (an accounts access token; the
server consumes it, starts a session, and strips the parameter — `catlab-accounts
src/Accounts/Module.php:184-207`). The already-vendored
`CatLab\Accounts\Client\ApiClient::getAccountLink($path, $parameters = [])` builds
exactly such links: `{services.catlab.url}{path}?authcode={catlab_access_token}` —
currently unused in drinks.

Manage's navbar (`resources/manage/js/views/App.vue`) has a Settings dropdown; the blade
(`resources/views/client/manage.blade.php`) already injects per-request globals
(`ORGANISATION_ID`, `CATLAB_DRINKS_CONFIG.ACCOUNTS_URL`).

## Requirement

A "My CatLab Account" menu item in the Manage navbar that deep-links the user to their
accounts `/myaccount` page, already authenticated — shown **only** when the user logged
in through SSO.

"Logged in through SSO" = `config('services.catlab.client_id')` is set (SSO instance)
**and** the authenticated user has a non-null `catlab_id`. (Both conditions matter: a
founding local user can exist on an instance later configured for SSO.)

## Design

### Backend: `GET /account`

- Route in `routes/web.php` behind the `auth` middleware; controller
  `App\Http\Controllers\AccountLinkController@redirect`.
- SSO user (per the definition above, and with a non-empty `catlab_access_token`):
  `return redirect((new \CatLab\Accounts\Client\ApiClient($user))->getAccountLink('/myaccount'));`
- Anyone else (local user, SSO-configured instance without a linked account, missing
  token): `return redirect('/manage');`
- No HTTP call is involved — `getAccountLink` is pure URL building. The token appears
  once in the redirect URL; the accounts server consumes and strips it. The SPA never
  sees the token.

### Frontend: navbar item

- In `App.vue`'s Settings dropdown, above "Organisation Settings":
  a `b-dropdown-item` with `href="/account"`, `target="_blank"`, `rel="noopener"`,
  label `{{ $t('My CatLab Account') }}`, rendered only when
  `window.CATLAB_DRINKS_CONFIG.SSO_ACCOUNT` is truthy (captured into component data).
- `manage.blade.php` sets the flag next to `ACCOUNTS_URL`:
  `window.CATLAB_DRINKS_CONFIG.SSO_ACCOUNT = @json((bool)(config('services.catlab.client_id') && \Auth::user() && \Auth::user()->catlab_id !== null));`

### i18n

`'My CatLab Account'` key added to the locale files following their convention
(nl: `'Mijn CatLab account'`; fr/de/es analogous since those files enumerate all keys).

## Testing

Feature test `AccountLinkTest`:
- SSO user (client_id configured, `catlab_id` + `catlab_access_token` set) → 302 to
  `{accounts-url}/myaccount?authcode={token}`.
- Local user (no `catlab_id`) → 302 to `/manage`.
- SSO-linked user without a stored token → 302 to `/manage`.
- Guest → redirected to login (default `auth` behaviour).

No new JS tests: the frontend change is a template conditional on a boot global.

## Out of scope

- No return-link from accounts back to drinks (accounts `/myaccount` has no `return`
  support worth wiring here).
- POS app untouched; non-SSO instances see no change.
