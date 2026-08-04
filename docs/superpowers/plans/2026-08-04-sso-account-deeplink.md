# SSO Account Deeplink Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "My CatLab Account" item in the Manage navbar that deep-links SSO users to their accounts `/myaccount` page, auto-authenticated via the vendor `getAccountLink` mechanism.

**Architecture:** A `GET /account` route redirects SSO users to `{accounts}/myaccount?authcode={token}` (built by the vendored `CatLab\Accounts\Client\ApiClient`) and everyone else to `/manage`. The blade exposes an `SSO_ACCOUNT` boolean; the navbar renders the item only when it's true.

**Tech Stack:** Laravel 9, vendored `catlabinteractive/laravel-catlab-accounts`, Vue 3 compat + Bootstrap-Vue.

**Spec:** `docs/superpowers/specs/2026-08-04-sso-account-deeplink-design.md`.

## Global Constraints

- Branch: `feature/sso-account-deeplink` (stacked on `feature/profiles-organisations-sync`, already checked out).
- PHP tests: `vendor/bin/phpunit --filter <Class>`; falls back to `docker compose --profile test run --rm phpunit --filter <Class>` if the host has no PHP.
- "Logged in through SSO" = `config('services.catlab.client_id')` set **and** the user's `catlab_id` is non-null; the redirect additionally requires a non-empty `catlab_access_token`.
- The vendor builds `Config::get('services.catlab.url') . $path`. The config URL carries a trailing slash by default (`https://accounts.catlab.eu/` in `config/services.php`), so the controller calls `getAccountLink('myaccount')` — path without a leading slash — and its docblock documents the trailing-slash expectation. Tests set the config URL with the trailing slash to match.
- Vue/JS files use tabs; PHP app files use 4 spaces; GPL headers on new files.
- Never commit `package-lock.json`.

---

### Task 1: `GET /account` redirect + navbar item + i18n

**Files:**
- Create: `app/Http/Controllers/AccountLinkController.php`
- Modify: `routes/web.php` (add route), `resources/views/client/manage.blade.php` (add `SSO_ACCOUNT` flag), `resources/manage/js/views/App.vue` (Settings dropdown item + data flag), `resources/shared/js/i18n/nl.js`, `resources/shared/js/i18n/fr.js`, `resources/shared/js/i18n/de.js`, `resources/shared/js/i18n/es.js`, `resources/shared/js/i18n/en.js` (only if it enumerates all keys — check)
- Test: `tests/Feature/AccountLinkTest.php` (new)

**Interfaces:**
- Consumes: vendored `CatLab\Accounts\Client\ApiClient::__construct($user)` + `getAccountLink(string $path, array $parameters = []): string`; `users.catlab_id`, `users.catlab_access_token`; blade global pattern `window.CATLAB_DRINKS_CONFIG.*`.
- Produces: route `GET /account` (auth); blade global `window.CATLAB_DRINKS_CONFIG.SSO_ACCOUNT` (boolean).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AccountLinkTest.php` (GPL header copied from another test file):

```php
<?php
/* (GPL header) */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests the SSO account deeplink redirect (GET /account).
 */
class AccountLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.catlab.url' => 'https://accounts.test/',
            'services.catlab.client_id' => 'test-client',
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function testSsoUserIsRedirectedToAccountsWithAuthcode(): void
    {
        $user = $this->makeUser();
        $user->catlab_id = 4242;
        $user->catlab_access_token = 'token-4242';
        $user->save();

        $response = $this->actingAs($user->fresh())->get('/account');

        $response->assertRedirect('https://accounts.test/myaccount?authcode=token-4242');
    }

    public function testLocalUserIsRedirectedToManage(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/account');

        $response->assertRedirect('/manage');
    }

    public function testSsoUserWithoutTokenIsRedirectedToManage(): void
    {
        $user = $this->makeUser();
        $user->catlab_id = 4243;
        $user->save();

        $response = $this->actingAs($user->fresh())->get('/account');

        $response->assertRedirect('/manage');
    }

    public function testNonSsoInstanceRedirectsToManage(): void
    {
        config(['services.catlab.client_id' => null]);
        $user = $this->makeUser();
        $user->catlab_id = 4244;
        $user->catlab_access_token = 'token-4244';
        $user->save();

        $response = $this->actingAs($user->fresh())->get('/account');

        $response->assertRedirect('/manage');
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $response = $this->get('/account');

        $response->assertStatus(302);
        $this->assertGuest();
    }
}
```

Note on the expected URL: the config URL carries a trailing slash by convention
(`config/services.php` default is `https://accounts.catlab.eu/`), and the controller
passes the path without a leading slash, so the result has exactly one slash.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter AccountLinkTest`
Expected: FAIL — `/account` returns 404 (route missing).

- [ ] **Step 3: Implement controller + route**

Create `app/Http/Controllers/AccountLinkController.php`:

```php
<?php
/* (GPL header) */

namespace App\Http\Controllers;

use CatLab\Accounts\Client\ApiClient;

/**
 * Deep-links SSO users to their CatLab accounts page (change password,
 * email, billing) via the accounts authcode login mechanism.
 *
 * Note: services.catlab.url is expected to carry its default trailing
 * slash (config/services.php), so the path is passed without one.
 */
class AccountLinkController
{
    public function redirect()
    {
        $user = \Auth::user();

        if (!config('services.catlab.client_id')
            || !$user
            || $user->catlab_id === null
            || empty($user->catlab_access_token)) {
            return redirect('/manage');
        }

        return redirect((new ApiClient($user))->getAccountLink('myaccount'));
    }
}
```

In `routes/web.php`, next to the license-return route (before the `/manage/{any?}` catch-all is fine anywhere — the path doesn't collide):

```php
/*
 * Deeplink to the CatLab accounts "my account" page (SSO users only).
 */
Route::get('/account', 'AccountLinkController@redirect')
    ->middleware('auth');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter AccountLinkTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Blade flag**

In `resources/views/client/manage.blade.php`, extend the existing inline script block with one line after the `ACCOUNTS_URL` assignment:

```html
    window.CATLAB_DRINKS_CONFIG.SSO_ACCOUNT = @json((bool)(config('services.catlab.client_id') && \Auth::user() && \Auth::user()->catlab_id !== null));
```

- [ ] **Step 6: Navbar item**

In `resources/manage/js/views/App.vue` (tabs), inside the Settings dropdown, add a first item above "Organisation Settings":

```html
						<b-nav-item-dropdown :text="$t('Settings')" right>
							<b-dropdown-item v-if="ssoAccount" href="/account" target="_blank" rel="noopener">{{ $t('My CatLab Account') }}</b-dropdown-item>
							<b-dropdown-item :to="{ name: 'settings' }">{{ $t('Organisation Settings') }}</b-dropdown-item>
							<b-dropdown-item :to="{ name: 'publicKeys' }">{{ $t('Public Keys') }}</b-dropdown-item>
						</b-nav-item-dropdown>
```

and add to the component's `data()` return object:

```js
				ssoAccount: !!(window.CATLAB_DRINKS_CONFIG && window.CATLAB_DRINKS_CONFIG.SSO_ACCOUNT),
```

- [ ] **Step 7: i18n**

Add the `'My CatLab Account'` key to each locale file that enumerates all keys, next to the existing `'Rename on CatLab Accounts'` entry (nl.js line ~385):

- nl: `'My CatLab Account': 'Mijn CatLab account',`
- fr: `'My CatLab Account': 'Mon compte CatLab',`
- de: `'My CatLab Account': 'Mein CatLab-Konto',`
- es: `'My CatLab Account': 'Mi cuenta de CatLab',`
- en: `'My CatLab Account': 'My CatLab Account',` (only if en.js enumerates all keys — follow whatever `'Rename on CatLab Accounts'` did there)

- [ ] **Step 8: Build + full suites**

```bash
npm run dev
npx vitest run
npx jest
vendor/bin/phpunit
git checkout -- package-lock.json
```

Expected: build succeeds, all suites green (287 PHP tests: 282 + 5 new).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/AccountLinkController.php routes/web.php resources/views/client/manage.blade.php resources/manage/js/views/App.vue resources/shared/js/i18n/ tests/Feature/AccountLinkTest.php
git commit -m "Add SSO account deeplink to the Manage menu"
```
