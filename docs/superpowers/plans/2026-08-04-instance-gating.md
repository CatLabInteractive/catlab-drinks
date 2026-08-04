# Instance Gating, Registration Lockdown & First-Run Setup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gate shared instances (testing-mode warning for non-whitelisted organisations), lock down registration after the first user, add a first-run setup page, a database-error help page, and matching docs — all driven by two new env vars, fully covered by automated tests running in the existing GitHub Actions CI.

**Architecture:** A new `config/instance.php` (fed by `PRODUCTION_ORGANISATION_IDS` and `REGISTRATION_OPEN`) is consumed by a small `App\Support\InstanceSettings` helper. `Organisation` gains a `test_mode` computed attribute exposed through the Management API's existing `users/me` boot call, which the Manage Vue app renders as a persistent banner. Registration is guarded in `RegisterController` (local auth) and an SSO `LoginController` subclass; a `RedirectIfSetupRequired` middleware sends visitors of empty instances to a one-page `/setup` flow. The exception handler renders a static help page for database connection/migration errors.

**Tech Stack:** Laravel 9 (PHP ^8.1), CatLab Charon, PHPUnit 9 (RefreshDatabase + MySQL test DB), Vue 3 compat + Bootstrap-Vue, GitHub Actions (existing `tests.yml`).

**Spec:** `docs/superpowers/specs/2026-08-04-instance-gating-design.md`

## Global Constraints

- PHP files start with the project's GPL header comment. Copy it **verbatim** from the top of `app/Models/Organisation.php` (lines 2–21: "CatLab Drinks - Simple bar automation system … 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA."). Referred to below as `[GPL HEADER]`.
- Match each file's existing indentation style (some files use tabs, some 4 spaces). New files: 4 spaces.
- Never run `npm update`. If you run `npm install`, revert lockfile changes afterwards: `git checkout -- package-lock.json`. Never commit `package-lock.json`.
- Run PHP tests with `vendor/bin/phpunit --filter <TestClass>`; the full suite with `vendor/bin/phpunit`. The test DB is MySQL `catlab_drinks_test` at 127.0.0.1:3306, user `test`/`test` (see `phpunit.xml`). Feature tests use the `RefreshDatabase` trait (project convention — it migrates automatically).
- If `composer`/`php` complain about PHP version, use `--ignore-platform-reqs` (vendor/ is already installed; don't reinstall unless needed).
- Existing CI (`.github/workflows/tests.yml`) runs `vendor/bin/phpunit` and `npm test` on push/PR to main/master/develop. Do not modify the workflow.
- New user-facing strings: wrap in `__('...')` (blade) / `$t('...')` (Vue) with the English text as the key, matching existing usage. No translation files need updating.
- Commit after every task with the trailer:
  ```
  Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01ETXGGmbDFTxnLdJrm1gjF5
  ```

---

### Task 0: Branch + test environment sanity check

**Files:** none (environment only)

- [ ] **Step 1: Create feature branch**

```bash
cd /home/daedeloth/Workbench/catlab-drinks
git checkout -b feature/instance-gating
```

- [ ] **Step 2: Verify the PHP test environment works**

```bash
vendor/bin/phpunit --filter ExampleTest
```

Expected: PASS (green). If it fails because MySQL is unreachable, start a disposable test DB and retry:

```bash
docker run --name catlab-test-mysql -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=catlab_drinks_test -e MYSQL_USER=test -e MYSQL_PASSWORD=test \
  -p 3306:3306 -d mysql:8.0
# wait ~20s for it to come up, then retry phpunit
```

(No migrate step needed — `RefreshDatabase` migrates on first use.)

- [ ] **Step 3: Verify frontend tests pass as baseline**

```bash
npm test
```

Expected: PASS. If `node_modules` is missing: `npm install` then `git checkout -- package-lock.json`.

---

### Task 1: InstanceSettings helper + config/instance.php

**Files:**
- Create: `app/Support/InstanceSettings.php`
- Create: `config/instance.php`
- Test: `tests/Unit/InstanceConfigTest.php`

**Interfaces:**
- Produces: `App\Support\InstanceSettings::parseIdList(?string $value): array` (of int); `InstanceSettings::isSetupRequired(): bool`; `InstanceSettings::isRegistrationOpen(): bool`; config keys `instance.production_organisation_ids` (int[]) and `instance.registration_open` (bool). All later tasks consume these.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/InstanceConfigTest.php` (plain PHPUnit — `parseIdList` is pure):

```php
<?php
[GPL HEADER]

namespace Tests\Unit;

use App\Support\InstanceSettings;
use PHPUnit\Framework\TestCase;

/**
 * Tests for parsing of instance-level configuration values.
 */
class InstanceConfigTest extends TestCase
{
    public function testParsesCommaSeparatedIds(): void
    {
        $this->assertSame([1, 5, 42], InstanceSettings::parseIdList('1,5,42'));
    }

    public function testToleratesWhitespace(): void
    {
        $this->assertSame([1, 5], InstanceSettings::parseIdList(' 1 , 5 '));
    }

    public function testEmptyStringGivesEmptyList(): void
    {
        $this->assertSame([], InstanceSettings::parseIdList(''));
    }

    public function testNullGivesEmptyList(): void
    {
        $this->assertSame([], InstanceSettings::parseIdList(null));
    }

    public function testIgnoresNonNumericEntries(): void
    {
        $this->assertSame([3], InstanceSettings::parseIdList('abc, 3, ,x1'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter InstanceConfigTest`
Expected: ERROR — class `App\Support\InstanceSettings` not found.

- [ ] **Step 3: Implement InstanceSettings**

`app/Support/InstanceSettings.php`:

```php
<?php
[GPL HEADER]

namespace App\Support;

use App\Models\User;

/**
 * Instance-level settings: which organisations may use this instance in
 * production, and whether public registration is open.
 */
class InstanceSettings
{
    /**
     * Parse a comma-separated list of numeric IDs into an array of ints.
     * Whitespace is tolerated; non-numeric entries are ignored.
     *
     * @param string|null $value
     * @return int[]
     */
    public static function parseIdList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }

        return $ids;
    }

    /**
     * The first-run setup page is required while no users exist.
     * SSO instances never use the local setup page: their first login
     * through the SSO creates the founding user instead.
     *
     * @return bool
     */
    public static function isSetupRequired(): bool
    {
        if (config('services.catlab.client_id')) {
            return false;
        }

        return User::count() === 0;
    }

    /**
     * Registration is open while no users exist (the founder must be able
     * to register) or when the instance explicitly keeps it open.
     *
     * @return bool
     */
    public static function isRegistrationOpen(): bool
    {
        if (config('instance.registration_open')) {
            return true;
        }

        return User::count() === 0;
    }
}
```

`config/instance.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Production organisations
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of organisation IDs that use this instance in
    | production. When set, all other organisations see a warning in the
    | admin panel that this instance is for testing only. When empty, the
    | instance is considered private and all organisations are production.
    |
    */
    'production_organisation_ids' => \App\Support\InstanceSettings::parseIdList(
        env('PRODUCTION_ORGANISATION_IDS')
    ),

    /*
    |--------------------------------------------------------------------------
    | Open registration
    |--------------------------------------------------------------------------
    |
    | By default, registration closes automatically once the first user has
    | been created. Set to true to keep public registration open (as on the
    | shared instance drinks.catlab.eu).
    |
    */
    'registration_open' => (bool) env('REGISTRATION_OPEN', false),

];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter InstanceConfigTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/InstanceSettings.php config/instance.php tests/Unit/InstanceConfigTest.php
git commit -m "Add instance settings: production organisation whitelist and registration flag"
```

---

### Task 2: Organisation production flag + test_mode API field

**Files:**
- Modify: `app/Models/Organisation.php` (add two methods at the end of the class, after `getTopupDomainAttribute`)
- Modify: `app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php` (add field in constructor)
- Test: `tests/Feature/OrganisationTestModeTest.php`

**Interfaces:**
- Consumes: `config('instance.production_organisation_ids')` (Task 1).
- Produces: `Organisation::isProductionAllowed(): bool`; `Organisation->test_mode` (bool accessor); `test_mode` field on the Management API organisation resource (appears in `GET /api/v1/users/me` under `organisations.items[n].test_mode`). Task 7 (banner) consumes the API field.

- [ ] **Step 1: Write the failing test**

`tests/Feature/OrganisationTestModeTest.php`:

```php
<?php
[GPL HEADER]

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Tests for the production-organisation whitelist and the test_mode API field.
 */
class OrganisationTestModeTest extends TestCase
{
    use RefreshDatabase;

    public function testAllOrganisationsAreProductionWhenListIsEmpty(): void
    {
        config(['instance.production_organisation_ids' => []]);

        $organisation = Organisation::factory()->create();

        $this->assertTrue($organisation->isProductionAllowed());
        $this->assertFalse($organisation->test_mode);
    }

    public function testListedOrganisationIsProduction(): void
    {
        $organisation = Organisation::factory()->create();
        config(['instance.production_organisation_ids' => [$organisation->id]]);

        $this->assertTrue($organisation->isProductionAllowed());
        $this->assertFalse($organisation->test_mode);
    }

    public function testUnlistedOrganisationIsTestMode(): void
    {
        $organisation = Organisation::factory()->create();
        config(['instance.production_organisation_ids' => [$organisation->id + 1000]]);

        $this->assertFalse($organisation->isProductionAllowed());
        $this->assertTrue($organisation->test_mode);
    }

    public function testTestModeIsExposedInUsersMeApi(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        // The User::created event auto-creates an organisation.
        $organisation = $user->organisations()->first();

        config(['instance.production_organisation_ids' => [$organisation->id + 1000]]);

        Passport::actingAs($user);
        $response = $this->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJsonPath('organisations.items.0.test_mode', true);
    }

    public function testTestModeFalseInUsersMeApiWhenWhitelisted(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $organisation = $user->organisations()->first();

        config(['instance.production_organisation_ids' => [$organisation->id]]);

        Passport::actingAs($user);
        $response = $this->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJsonPath('organisations.items.0.test_mode', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter OrganisationTestModeTest`
Expected: FAIL — `isProductionAllowed` undefined / `test_mode` missing from JSON.

- [ ] **Step 3: Implement model methods and API field**

In `app/Models/Organisation.php`, after `getTopupDomainAttribute()` (before the closing brace; this file uses 4-space indentation in that region):

```php
    /**
     * Is this organisation allowed to use this instance in production?
     * True when no whitelist is configured (private instance) or when the
     * organisation is on the whitelist.
     * @return bool
     */
    public function isProductionAllowed(): bool
    {
        $ids = config('instance.production_organisation_ids', []);
        if (empty($ids)) {
            return true;
        }

        return in_array($this->id, $ids, true);
    }

    /**
     * Inverse of isProductionAllowed, exposed to the API as test_mode.
     * @return bool
     */
    public function getTestModeAttribute(): bool
    {
        return !$this->isProductionAllowed();
    }
```

In `app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php`, after the `min_nfc_version` field block:

```php
        $this->field('test_mode')
            ->bool()
            ->visible(true)
            ->writeable(false, false);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter OrganisationTestModeTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Organisation.php app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php tests/Feature/OrganisationTestModeTest.php
git commit -m "Expose organisation test_mode flag based on production whitelist"
```

---

### Task 3: First-run setup flow

**Files:**
- Create: `app/Http/Middleware/RedirectIfSetupRequired.php`
- Create: `app/Http/Controllers/SetupController.php`
- Create: `resources/views/setup.blade.php`
- Modify: `app/Http/Kernel.php:76-86` (add route middleware alias)
- Modify: `routes/web.php` (setup routes; attach middleware to `/`, `/home`, `/manage`; wrap auth routes)
- Test: `tests/Feature/SetupControllerTest.php`

**Interfaces:**
- Consumes: `InstanceSettings::isSetupRequired()` (Task 1).
- Produces: routes `GET|POST /setup` (name `setup`); middleware alias `setup.redirect`. Task 4's tests rely on `/register` being wrapped with `setup.redirect`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SetupControllerTest.php`:

```php
<?php
[GPL HEADER]

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the first-run setup flow on instances without any users.
 */
class SetupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Founder',
            'email' => 'founder@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'organisation_name' => 'My Bar',
        ], $overrides);
    }

    public function testEntryPointsRedirectToSetupWhenNoUsersExist(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/register')->assertRedirect('/setup');
        $this->get('/manage')->assertRedirect('/setup');
    }

    public function testSetupPageShowsForm(): void
    {
        $response = $this->get('/setup');

        $response->assertStatus(200);
        $response->assertSee('organisation_name');
    }

    public function testSetupCreatesUserAndRenamesOrganisation(): void
    {
        $response = $this->post('/setup', $this->validPayload());

        $response->assertRedirect('/getting-started');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'founder@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('My Bar', $user->organisations()->first()->name);
    }

    public function testSetupRedirectsAwayWhenUserExists(): void
    {
        $this->post('/setup', $this->validPayload());
        auth()->logout();

        $this->get('/setup')->assertRedirect('/login');

        $this->post('/setup', $this->validPayload([
            'email' => 'second@example.com',
        ]))->assertRedirect('/login');

        $this->assertEquals(1, User::count());
    }

    public function testEntryPointsBehaveNormallyOnceUserExists(): void
    {
        $this->post('/setup', $this->validPayload());
        auth()->logout();

        $this->get('/')->assertStatus(200);
        $this->get('/login')->assertStatus(200);
    }

    public function testNoSetupRedirectOnSsoInstances(): void
    {
        config(['services.catlab.client_id' => 'sso-client']);

        $this->get('/')->assertStatus(200);
        $this->get('/setup')->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SetupControllerTest`
Expected: FAIL — `/setup` returns 404, entry points don't redirect.

- [ ] **Step 3: Implement middleware, controller, view, routes**

`app/Http/Middleware/RedirectIfSetupRequired.php`:

```php
<?php
[GPL HEADER]

namespace App\Http\Middleware;

use App\Support\InstanceSettings;
use Closure;
use Illuminate\Http\Request;

/**
 * Redirect to the first-run setup page while the instance has no users.
 */
class RedirectIfSetupRequired
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (InstanceSettings::isSetupRequired()) {
            return redirect('/setup');
        }

        return $next($request);
    }
}
```

`app/Http/Controllers/SetupController.php`:

```php
<?php
[GPL HEADER]

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\InstanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * First-run setup: create the founding user and organisation on a fresh
 * instance. Only accessible while no users exist.
 */
class SetupController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showSetupForm()
    {
        if (!InstanceSettings::isSetupRequired()) {
            return redirect('/login');
        }

        return view('setup');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSetup(Request $request)
    {
        if (!InstanceSettings::isSetupRequired()) {
            return redirect('/login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'organisation_name' => ['required', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            // Lock the users table so two concurrent setup submissions
            // cannot both create a founding user.
            if (DB::table('users')->lockForUpdate()->count() > 0) {
                return null;
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // The User::created event auto-creates an organisation named
            // after the user; rename it to the submitted organisation name.
            $organisation = $user->organisations()->first();
            if ($organisation) {
                $organisation->name = $data['organisation_name'];
                $organisation->save();
            }

            return $user;
        });

        if (!$user) {
            return redirect('/login');
        }

        Auth::login($user);

        return redirect('/getting-started');
    }
}
```

`resources/views/setup.blade.php` (modeled on `resources/views/auth/register.blade.php`):

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Welcome to your new CatLab Drinks instance') }}</div>

                <div class="card-body">
                    <p>{{ __('This instance has no users yet. Create your administrator account and organisation to get started.') }}</p>

                    <form method="POST" action="{{ url('/setup') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="name" class="col-md-4 col-form-label text-md-right">{{ __('Your name') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name" value="{{ old('name') }}" required autofocus>

                                @if ($errors->has('name'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" required>

                                @if ($errors->has('email'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" required>

                                @if ($errors->has('password'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password-confirm" class="col-md-4 col-form-label text-md-right">{{ __('Confirm Password') }}</label>

                            <div class="col-md-6">
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="organisation_name" class="col-md-4 col-form-label text-md-right">{{ __('Organisation name') }}</label>

                            <div class="col-md-6">
                                <input id="organisation_name" type="text" class="form-control{{ $errors->has('organisation_name') ? ' is-invalid' : '' }}" name="organisation_name" value="{{ old('organisation_name') }}" required>

                                @if ($errors->has('organisation_name'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('organisation_name') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Create account') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

In `app/Http/Kernel.php`, add to `$routeMiddleware` (after the `'signed'` entry, keeping alphabetical-ish order):

```php
        'setup.redirect' => \App\Http\Middleware\RedirectIfSetupRequired::class,
```

In `routes/web.php`:

1. Change line 14 from `Route::get('/', 'HomeController@welcome');` to:

```php
Route::get('/', 'HomeController@welcome')->middleware('setup.redirect');
```

2. Change the `/manage/{any?}` route (lines 33–35) to:

```php
Route::get('/manage/{any?}', 'ClientController@manage')
    ->where('any', '.*')
    ->middleware(['setup.redirect', 'auth']);
```

3. Replace the auth-routes block (lines 55–61) with:

```php
// Do we have catlab client id? (my own personal single sign on service)
Route::group(['middleware' => 'setup.redirect'], function () {
    if (config('services.catlab.client_id')) {
        \CatLab\Accounts\Client\Controllers\LoginController::setRoutes();
    } else {
        // Not set? Use default laravel authentication.
        Auth::routes();
    }
});
```

4. Change the `/home` route to:

```php
Route::get('/home', 'HomeController@index')->name('home')->middleware('setup.redirect');
```

5. Add the setup routes directly above the auth-routes block:

```php
/*
 * First-run setup (only accessible while the instance has no users)
 */
Route::get('/setup', 'SetupController@showSetupForm')->name('setup');
Route::post('/setup', 'SetupController@processSetup');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SetupControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Run the whole suite to catch regressions**

Run: `vendor/bin/phpunit`
Expected: PASS. Watch specifically for existing tests that hit `/`, `/login` or `/register` — they run with a `RefreshDatabase`-fresh (empty-users) DB and will now be redirected to `/setup`. Based on current tests, only `AuthLicenseWarrantyTest` is likely affected (it asserts the license warning on the `/register` page). If it fails, add to its setUp **both** of:

```php
User::query()->create([
    'name' => 'Existing User',
    'email' => 'existing-' . Str::random(8) . '@example.com',
    'password' => bcrypt('secret'),
]);
config(['instance.registration_open' => true]);
```

The user takes the instance out of first-run setup (otherwise `/register` redirects to `/setup`), and the config flag keeps registration open (otherwise `/register` returns 403 after Task 4). Add missing imports (`App\Models\User`, `Illuminate\Support\Str`) as needed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/RedirectIfSetupRequired.php app/Http/Controllers/SetupController.php resources/views/setup.blade.php app/Http/Kernel.php routes/web.php tests/Feature/SetupControllerTest.php
git commit -m "Add first-run setup flow for fresh instances"
```

---

### Task 4: Registration lockdown (local auth)

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisterController.php`
- Create: `resources/views/auth/registration-closed.blade.php`
- Modify: `resources/views/layouts/app.blade.php:52` (register link condition)
- Test: `tests/Feature/RegistrationLockdownTest.php`

**Interfaces:**
- Consumes: `InstanceSettings::isRegistrationOpen()` (Task 1); `setup.redirect`-wrapped `/register` routes (Task 3).
- Produces: view `auth.registration-closed` (also used by Task 5).

- [ ] **Step 1: Write the failing test**

`tests/Feature/RegistrationLockdownTest.php`:

```php
<?php
[GPL HEADER]

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests that public registration is closed once a user exists, unless
 * REGISTRATION_OPEN is set.
 */
class RegistrationLockdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // An existing user means the instance is past first-run setup.
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function testRegisterPageClosedByDefault(): void
    {
        config(['instance.registration_open' => false]);

        $response = $this->get('/register');

        $response->assertStatus(403);
        $response->assertSee(__('Registration is closed on this instance.'));
    }

    public function testRegisterPostBlockedByDefault(): void
    {
        config(['instance.registration_open' => false]);

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(403);
        $this->assertNull(User::query()->where('email', 'new@example.com')->first());
    }

    public function testRegisterOpenWhenConfigured(): void
    {
        config(['instance.registration_open' => true]);

        $this->get('/register')->assertStatus(200);

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect('/manage');
        $this->assertNotNull(User::query()->where('email', 'new@example.com')->first());
    }

    public function testRegisterLinkHiddenWhenClosed(): void
    {
        config(['instance.registration_open' => false]);
        $this->get('/login')->assertDontSee('href="' . route('register') . '"', false);

        config(['instance.registration_open' => true]);
        $this->get('/login')->assertSee('href="' . route('register') . '"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter RegistrationLockdownTest`
Expected: FAIL — `/register` returns 200/302 instead of 403.

- [ ] **Step 3: Implement the lockdown**

In `app/Http/Controllers/Auth/RegisterController.php`:

1. Add imports below the existing ones (`use Illuminate\Support\Facades\Validator;`):

```php
use App\Support\InstanceSettings;
use Illuminate\Http\Request;
```

2. Change the trait import line to alias the trait's register method:

```php
    use RegistersUsers {
        register as traitRegister;
    }
```

3. Add these two methods after the constructor:

```php
    /**
     * Show the registration form, or the "registration closed" page when
     * this instance does not accept new registrations.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function showRegistrationForm()
    {
        if (!InstanceSettings::isRegistrationOpen()) {
            return response()->view('auth.registration-closed', [], 403);
        }

        return view('auth.register');
    }

    /**
     * Handle a registration request, unless registration is closed.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        if (!InstanceSettings::isRegistrationOpen()) {
            return response()->view('auth.registration-closed', [], 403);
        }

        return $this->traitRegister($request);
    }
```

`resources/views/auth/registration-closed.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Registration closed') }}</div>

                <div class="card-body">
                    <p>{{ __('Registration is closed on this instance.') }}</p>
                    <p>
                        {{ __('CatLab Drinks is open source software: you can set up your own instance for free.') }}
                        <a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance">{{ __('See the setup instructions to get started.') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

In `resources/views/layouts/app.blade.php` line 52, change:

```blade
                            @if (Route::has('register'))
```

to:

```blade
                            @if (Route::has('register') && \App\Support\InstanceSettings::isRegistrationOpen())
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter RegistrationLockdownTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/RegisterController.php resources/views/auth/registration-closed.blade.php resources/views/layouts/app.blade.php tests/Feature/RegistrationLockdownTest.php
git commit -m "Close public registration once the first user exists"
```

---

### Task 5: Registration gate for SSO logins

**Files:**
- Create: `app/Http/Controllers/Auth/SsoLoginController.php`
- Modify: `routes/web.php` (SSO branch registers subclass routes)
- Test: `tests/Feature/SsoRegistrationGateTest.php`

**Interfaces:**
- Consumes: `InstanceSettings::isRegistrationOpen()` (Task 1); view `auth.registration-closed` (Task 4); vendor class `\CatLab\Accounts\Client\Controllers\LoginController` (methods `getUserFromSocialite($socialiteUser)`, `getUserFromCatLabId($id)`, `postLogin()`, `login()`, `logout()` — see `vendor/catlabinteractive/laravel-catlab-accounts/src/Controllers/LoginController.php`).
- Produces: `App\Http\Controllers\Auth\SsoLoginController` used by the SSO route branch.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SsoRegistrationGateTest.php`. The SSO callback route only exists when `services.catlab.client_id` is set at boot, so the test registers a route to the controller directly and mocks Socialite:

```php
<?php
[GPL HEADER]

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

/**
 * Tests that first-time SSO logins respect the registration lockdown.
 */
class SsoRegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/sso-callback', [
            \App\Http\Controllers\Auth\SsoLoginController::class,
            'postLogin',
        ])->middleware('web');
    }

    private function mockSocialiteUser(string $catlabId): void
    {
        $socialiteUser = new \Laravel\Socialite\Two\User();
        $socialiteUser->map([
            'id' => $catlabId,
            'nickname' => 'ssouser',
            'name' => 'SSO User',
            'email' => 'sso-' . Str::random(8) . '@example.com',
            'token' => 'fake-token',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
    }

    public function testNewSsoUserRejectedWhenRegistrationClosed(): void
    {
        // An existing user closes registration.
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        config(['instance.registration_open' => false]);

        $this->mockSocialiteUser('catlab-123');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(403);
        $this->assertNull(User::query()->where('catlab_id', 'catlab-123')->first());
        $this->assertGuest();
    }

    public function testNewSsoUserAcceptedWhenRegistrationOpen(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        config(['instance.registration_open' => true]);

        $this->mockSocialiteUser('catlab-456');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertNotNull(User::query()->where('catlab_id', 'catlab-456')->first());
    }

    public function testFirstSsoUserAcceptedOnEmptyInstance(): void
    {
        config(['instance.registration_open' => false]);

        $this->mockSocialiteUser('catlab-789');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertNotNull(User::query()->where('catlab_id', 'catlab-789')->first());
    }

    public function testExistingSsoUserStillLogsInWhenClosed(): void
    {
        $existing = User::query()->create([
            'name' => 'SSO Veteran',
            'email' => 'veteran-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $existing->catlab_id = 'catlab-999';
        $existing->save();

        config(['instance.registration_open' => false]);

        $this->mockSocialiteUser('catlab-999');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertAuthenticated();
    }
}
```

Note: `users.catlab_id` exists via the package migration (`add_catlab_sso_fields`). If `$existing->catlab_id = ...` fails because the column is guarded, use `DB::table('users')->where('id', $existing->id)->update(['catlab_id' => 'catlab-999']);` instead.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SsoRegistrationGateTest`
Expected: ERROR — class `App\Http\Controllers\Auth\SsoLoginController` not found.

- [ ] **Step 3: Implement the SSO subclass and routes**

`app/Http/Controllers/Auth/SsoLoginController.php`:

```php
<?php
[GPL HEADER]

namespace App\Http\Controllers\Auth;

use App\Support\InstanceSettings;
use CatLab\Accounts\Client\Controllers\LoginController as CatLabLoginController;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * SSO login controller that refuses to create new local users while
 * registration is closed. Existing users log in normally.
 */
class SsoLoginController extends CatLabLoginController
{
    /**
     * @param mixed $socialiteUser
     * @return mixed
     */
    protected function getUserFromSocialite($socialiteUser)
    {
        $existing = $this->getUserFromCatLabId($socialiteUser->getId());

        if (!$existing && !InstanceSettings::isRegistrationOpen()) {
            throw new HttpResponseException(
                response()->view('auth.registration-closed', [], 403)
            );
        }

        return parent::getUserFromSocialite($socialiteUser);
    }
}
```

In `routes/web.php`, change the SSO branch inside the `setup.redirect` group (from Task 3) so the routes point at the subclass instead of the package controller:

```php
// Do we have catlab client id? (my own personal single sign on service)
Route::group(['middleware' => 'setup.redirect'], function () {
    if (config('services.catlab.client_id')) {
        Route::get('/login', [\App\Http\Controllers\Auth\SsoLoginController::class, 'login'])->name('login');
        Route::get('/login/callback', [\App\Http\Controllers\Auth\SsoLoginController::class, 'postLogin']);
        Route::post('/logout', [\App\Http\Controllers\Auth\SsoLoginController::class, 'logout'])->name('logout');
    } else {
        // Not set? Use default laravel authentication.
        Auth::routes();
    }
});
```

(This mirrors `CatLab\Accounts\Client\Controllers\LoginController::setRoutes()`, which hardcodes the vendor class name in its action strings and therefore cannot be reused for a subclass.)

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SsoRegistrationGateTest`
Expected: PASS (4 tests). If `Socialite::shouldReceive('driver->user')` fails because of facade root resolution, use the explicit form:

```php
Socialite::shouldReceive('driver')->with('catlab')->andReturn(
    \Mockery::mock(['user' => $socialiteUser])
);
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/SsoLoginController.php routes/web.php tests/Feature/SsoRegistrationGateTest.php
git commit -m "Gate first-time SSO logins behind the registration lockdown"
```

---

### Task 6: Database-error help page

**Files:**
- Create: `app/Exceptions/DatabaseErrorClassifier.php`
- Create: `resources/views/errors/database.blade.php`
- Modify: `app/Exceptions/Handler.php` (render hook)
- Test: `tests/Feature/DatabaseErrorPageTest.php`

**Interfaces:**
- Consumes: nothing from other tasks (standalone).
- Produces: `App\Exceptions\DatabaseErrorClassifier::isDatabaseSetupError(\Throwable): bool`, `::isConnectionError(\Throwable): bool`, `::isMissingTableError(\Throwable): bool`; view `errors.database` (variable: `bool $missingTables`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/DatabaseErrorPageTest.php`:

```php
<?php
[GPL HEADER]

namespace Tests\Feature;

use App\Exceptions\DatabaseErrorClassifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests for the friendly database-error help page shown when the app
 * cannot reach its database or migrations have not been run.
 */
class DatabaseErrorPageTest extends TestCase
{
    private function connectionException(): QueryException
    {
        $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
        $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];

        return new QueryException('select 1', [], $pdo);
    }

    private function missingTableException(): QueryException
    {
        $pdo = new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'db.users' doesn't exist");
        $pdo->errorInfo = ['42S02', 1146, "Table 'db.users' doesn't exist"];

        return new QueryException('select * from `users`', [], $pdo);
    }

    public function testClassifiesConnectionErrors(): void
    {
        $e = $this->connectionException();

        $this->assertTrue(DatabaseErrorClassifier::isConnectionError($e));
        $this->assertTrue(DatabaseErrorClassifier::isDatabaseSetupError($e));
        $this->assertFalse(DatabaseErrorClassifier::isMissingTableError($e));
    }

    public function testClassifiesBarePdoConnectionError(): void
    {
        // Connection failures outside a query are bare PDOExceptions with
        // an int code and no errorInfo.
        $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused', 2002);

        $this->assertTrue(DatabaseErrorClassifier::isConnectionError($pdo));
    }

    public function testClassifiesMissingTables(): void
    {
        $e = $this->missingTableException();

        $this->assertTrue(DatabaseErrorClassifier::isMissingTableError($e));
        $this->assertTrue(DatabaseErrorClassifier::isDatabaseSetupError($e));
        $this->assertFalse(DatabaseErrorClassifier::isConnectionError($e));
    }

    public function testIgnoresOrdinaryExceptions(): void
    {
        $this->assertFalse(DatabaseErrorClassifier::isDatabaseSetupError(new \RuntimeException('nope')));
        $this->assertFalse(DatabaseErrorClassifier::isDatabaseSetupError(
            new QueryException('select 1', [], new \PDOException('Syntax error', 42000))
        ));
    }

    public function testRendersHelpPageForDatabaseErrors(): void
    {
        config(['app.debug' => false]);

        Route::get('/_test/db-error', function () {
            $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
            $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];
            throw new QueryException('select 1', [], $pdo);
        });

        $response = $this->get('/_test/db-error');

        $response->assertStatus(503);
        $response->assertSee('php artisan migrate');
        $response->assertSee('DB_');
    }

    public function testDebugModeKeepsNormalErrorPage(): void
    {
        config(['app.debug' => true]);

        Route::get('/_test/db-error-debug', function () {
            $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
            $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];
            throw new QueryException('select 1', [], $pdo);
        });

        $response = $this->get('/_test/db-error-debug');

        $response->assertStatus(500);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter DatabaseErrorPageTest`
Expected: ERROR — class `DatabaseErrorClassifier` not found.

- [ ] **Step 3: Implement classifier, view, handler hook**

`app/Exceptions/DatabaseErrorClassifier.php`:

```php
<?php
[GPL HEADER]

namespace App\Exceptions;

/**
 * Classifies exceptions that indicate the database is unreachable or has
 * not been migrated, so a friendly setup-help page can be shown instead
 * of a generic server error.
 */
class DatabaseErrorClassifier
{
    /**
     * MySQL driver error codes for connection-level failures:
     * 1044 access denied to database, 1045 access denied for user,
     * 1049 unknown database, 2002 connection refused / socket,
     * 2006 server has gone away, 2054 auth protocol mismatch.
     */
    private const CONNECTION_ERROR_CODES = [1044, 1045, 1049, 2002, 2006, 2054];

    /**
     * MySQL driver error code for "base table or view not found".
     */
    private const MISSING_TABLE_ERROR_CODE = 1146;

    /**
     * @param \Throwable $e
     * @return bool
     */
    public static function isDatabaseSetupError(\Throwable $e): bool
    {
        return self::isConnectionError($e) || self::isMissingTableError($e);
    }

    /**
     * @param \Throwable $e
     * @return bool
     */
    public static function isConnectionError(\Throwable $e): bool
    {
        $pdoException = self::findPdoException($e);
        if (!$pdoException) {
            return false;
        }

        $code = self::driverErrorCode($pdoException);

        return $code !== null && in_array($code, self::CONNECTION_ERROR_CODES, true);
    }

    /**
     * @param \Throwable $e
     * @return bool
     */
    public static function isMissingTableError(\Throwable $e): bool
    {
        $pdoException = self::findPdoException($e);
        if (!$pdoException) {
            return false;
        }

        return self::driverErrorCode($pdoException) === self::MISSING_TABLE_ERROR_CODE;
    }

    /**
     * @param \PDOException $e
     * @return int|null
     */
    private static function driverErrorCode(\PDOException $e): ?int
    {
        if (isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1])) {
            return (int) $e->errorInfo[1];
        }

        // Connection failures carry the driver code as the exception code.
        if (is_numeric($e->getCode())) {
            return (int) $e->getCode();
        }

        return null;
    }

    /**
     * @param \Throwable $e
     * @return \PDOException|null
     */
    private static function findPdoException(\Throwable $e): ?\PDOException
    {
        while ($e !== null) {
            if ($e instanceof \PDOException) {
                return $e;
            }
            $e = $e->getPrevious();
        }

        return null;
    }
}
```

`resources/views/errors/database.blade.php` — standalone HTML, **no** `layouts.app` (that layout touches auth/session, which may be unavailable). Modeled on `resources/views/getting-started.blade.php`'s header:

```blade
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Database not available — CatLab Drinks</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<header>
    <div class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container d-flex justify-content-between">
            <span class="navbar-brand d-flex align-items-center">
                <strong>CatLab Drinks</strong>
            </span>
        </div>
    </div>
</header>

<main class="container my-4">

    <h1>Database not available</h1>

    <p class="lead">
        The application could not @if (!empty($missingTables)) find its database tables @else connect to its database @endif.
        If you have just installed this instance, a few setup steps may still be missing.
    </p>

    <h2 class="h4 mt-4">Checklist</h2>
    <ol>
        <li class="mb-2">
            <strong>Check the database configuration.</strong><br>
            Make sure the <code>DATABASE_URL</code> environment variable (or the individual
            <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>,
            <code>DB_USERNAME</code> and <code>DB_PASSWORD</code> variables) point to a
            running database server.
        </li>
        <li class="mb-2">
            <strong>Make sure the database server is running</strong> and reachable from this
            application server.
        </li>
        <li class="mb-2">
            <strong>Run the database migrations:</strong><br>
            <code>php artisan migrate</code><br>
            On Heroku and Dokku this runs automatically on each deploy via the
            <code>release</code> process.
        </li>
    </ol>

    <p>
        See the
        <a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance">setup instructions</a>
        for a full walkthrough. Once the database is reachable, reload this page.
    </p>

</main>
</body>
</html>
```

In `app/Exceptions/Handler.php`, add to the top of `render()` (before the Charon handler block):

```php
        // Show a friendly setup-help page when the database is unreachable
        // or has not been migrated (common on fresh installations).
        if (!config('app.debug')
            && !$request->expectsJson()
            && DatabaseErrorClassifier::isDatabaseSetupError($exception)
        ) {
            return response()->view('errors.database', [
                'missingTables' => DatabaseErrorClassifier::isMissingTableError($exception),
            ], 503);
        }
```

(No import needed — same namespace `App\Exceptions`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter DatabaseErrorPageTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Exceptions/DatabaseErrorClassifier.php app/Exceptions/Handler.php resources/views/errors/database.blade.php tests/Feature/DatabaseErrorPageTest.php
git commit -m "Show setup-help page when the database is unreachable or unmigrated"
```

---

### Task 7: Testing-mode banner in the Manage app

**Files:**
- Modify: `resources/manage/js/app.js` (~line 175, inside the `users/me` then-block)
- Modify: `resources/manage/js/views/App.vue` (banner above `<router-view>`)

**Interfaces:**
- Consumes: `test_mode` field on `organisations.items[0]` in the `/api/v1/users/me` response (Task 2).
- Produces: `window.ORGANISATION_TEST_MODE` (bool) — internal to the Manage app.

- [ ] **Step 1: Capture the flag at boot**

In `resources/manage/js/app.js`, directly after `window.ORGANISATION_ID = response.data.organisations.items[0].id;` add (this file uses tabs):

```js
					window.ORGANISATION_TEST_MODE = !!response.data.organisations.items[0].test_mode;
```

- [ ] **Step 2: Render the banner**

In `resources/manage/js/views/App.vue` (uses tabs):

1. In the template, directly above `<router-view></router-view>`:

```html
		<b-alert v-if="testMode" show variant="warning" class="mb-0 text-center rounded-0">
			{{ $t('You are using this shared instance in testing mode. Feel free to try things out, but for production events please set up your own instance.') }}
			<a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance" target="_blank" rel="noopener" class="alert-link">{{ $t('How to set up your own instance') }}</a>
		</b-alert>
```

2. In the component's `data()`, add a `testMode` property next to `kioskMode`:

```js
		data() {
			return {
				kioskMode: false,
				testMode: !!window.ORGANISATION_TEST_MODE
			}
		}
```

- [ ] **Step 3: Verify the frontend builds and existing JS tests pass**

```bash
npm run dev
npm test
```

Expected: build succeeds, all JS tests pass. If `npm run dev` altered `package-lock.json`: `git checkout -- package-lock.json`.

- [ ] **Step 4: Commit**

```bash
git add resources/manage/js/app.js resources/manage/js/views/App.vue
git commit -m "Show testing-mode banner in Manage for non-whitelisted organisations"
```

---

### Task 8: Documentation & deploy manifests

**Files:**
- Modify: `readme.md` (new "Run your own instance" section + env var reference; fix the manual-setup register sentence)
- Modify: `CLAUDE.md` (testing section, instance config notes)
- Modify: `app.json` (two optional env entries)

**Interfaces:**
- Consumes: everything above (documents it). The GitHub anchor `#run-your-own-instance` is already linked from the banner (Task 7), `registration-closed` page (Task 4), and DB error page (Task 6) — the heading text below must stay exactly "Run your own instance".

- [ ] **Step 1: readme.md — add "Run your own instance" section**

Insert directly after the "Deploy" section (after the DigitalOcean note blockquote, before "Architecture"):

```markdown
Run your own instance
---------------------
The shared instance at [drinks.catlab.eu](https://drinks.catlab.eu) is free to use **for
testing**. For production events, set up your own instance:

1. **Deploy** using one of the options above (Heroku / DigitalOcean one-click), the
   [Docker Compose setup](#setup-with-docker), the [manual setup](#manual-setup-without-docker),
   or Dokku.
2. **Open your instance in a browser.** A fresh instance greets you with a first-run setup
   page where you create your administrator account and organisation. After that,
   registration closes automatically: nobody else can register on your instance unless you
   explicitly open it (see `REGISTRATION_OPEN` below).
3. **Back up your `APP_KEY`** immediately (see the warning below — losing it makes NFC
   cards unusable).
4. Optionally configure a [topup domain](#topup-domain) and [NFC reader](#nfc-cashless-topup).

If the app shows a "Database not available" page instead of the setup screen, follow the
checklist on that page: verify the database environment variables and run
`php artisan migrate`.

### Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_KEY` | — (required) | Encrypts secrets and NFC card data. Generate with `php artisan key:generate`. **Back it up.** |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Standard Laravel environment switches. |
| `DATABASE_URL` or `DB_*` | — (required) | Database connection (MySQL). |
| `REGISTRATION_OPEN` | `false` | Keep public registration open after the first user has been created. Leave unset for a private instance. |
| `PRODUCTION_ORGANISATION_IDS` | — (unset) | Comma-separated organisation IDs that use this instance in production. When set, all *other* organisations see a "testing mode" warning in the admin panel. Leave unset on private instances. |
| `TOPUP_DOMAIN_NAME` | — (unset) | Short domain written to NFC cards for topup links (see [Topup Domain](#topup-domain)). |
| `CATLAB_CLIENT_ID` / `CATLAB_CLIENT_SECRET` | — (unset) | Optional CatLab Accounts single sign-on. When set, login/registration is delegated to the SSO server and the first-run setup page is skipped (the first SSO login creates the founding user). |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | — | OAuth keys; alternative to `php artisan passport:keys` on ephemeral filesystems (Heroku/Dokku). |
| `MAIL_*` | log driver | Outgoing mail (password resets, verification). |
```

Note: verify the actual SSO env var names by checking `config/services.php` (`services.catlab.*`) and use the names found there in the table.

- [ ] **Step 2: readme.md — fix the manual-setup ending**

Change the line `You should now be able to register an account at the website.` (currently line 101) to:

```markdown
Open the website and you will be greeted by the first-run setup page, where you create
your administrator account and organisation.
```

- [ ] **Step 3: app.json — add optional env vars**

In the `"env"` object of `app.json`, after `"DB_PASSWORD"`, add:

```json
		"REGISTRATION_OPEN": {
			"description": "Keep public registration open after the first user has been created. Leave unset for a private instance.",
			"required": false
		},
		"PRODUCTION_ORGANISATION_IDS": {
			"description": "Comma-separated organisation IDs allowed to use this instance in production. Leave unset to allow all organisations.",
			"required": false
		}
```

(Do not modify `.do/deploy.template.yaml` — the defaults are correct for a fresh locked-down instance; DO users can add the optional vars later via the dashboard, as documented in the readme.)

- [ ] **Step 4: CLAUDE.md — update testing + add instance config notes**

1. Replace the two lines under "### Backend" that read:

```
No automated test suite exists currently. Manual testing is required.
The lock file requires PHP ~8.1 or ~8.2; use `--ignore-platform-reqs` on newer PHP versions.
```

with:

```markdown
The lock file requires PHP ~8.1 or ~8.2; use `--ignore-platform-reqs` on newer PHP versions.

### PHP Tests
```bash
vendor/bin/phpunit                      # Full suite (needs MySQL test DB, see phpunit.xml)
vendor/bin/phpunit --filter FooTest     # Single test class
```
Feature tests use the `RefreshDatabase` trait against the `catlab_drinks_test` MySQL
database (127.0.0.1:3306, user `test`/`test`). GitHub Actions (`.github/workflows/tests.yml`)
runs the full PHP suite (PHP 8.1/8.2/8.3 matrix + MySQL 8 service) and `npm test` on every
push/PR to main/master/develop.
```

2. Add a new section before "## Key Models":

```markdown
## Instance Configuration

- `config/instance.php` + `App\Support\InstanceSettings`: `PRODUCTION_ORGANISATION_IDS`
  (comma-separated org IDs; orgs not on the list get `test_mode = true` on the Management
  API organisation resource and see a testing-mode banner in Manage) and
  `REGISTRATION_OPEN` (keeps registration open after the first user; default closed).
- First-run setup: while no users exist (and no SSO is configured), the
  `setup.redirect` middleware sends `/`, `/home`, `/login`, `/register`, `/manage` to
  `/setup` (`SetupController`), which creates the founding user + organisation.
- Registration gates live in `Auth\RegisterController` (local) and
  `Auth\SsoLoginController` (SSO first-login); both render `auth.registration-closed`.
- Database connection/migration failures render `errors/database.blade.php` via
  `App\Exceptions\DatabaseErrorClassifier` in the exception handler (non-debug only).
```

- [ ] **Step 5: Verify anchor consistency**

Check that the readme heading "Run your own instance" produces the GitHub anchor `#run-your-own-instance` used in: `App.vue` (Task 7), `registration-closed.blade.php` (Task 4), `errors/database.blade.php` (Task 6). Adjust links if the heading was worded differently.

- [ ] **Step 6: Commit**

```bash
git add readme.md CLAUDE.md app.json
git commit -m "Document self-hosting, instance env vars and test suite"
```

---

### Task 9: Full verification + CI

**Files:** none (verification only)

- [ ] **Step 1: Run the complete PHP suite**

Run: `vendor/bin/phpunit`
Expected: PASS — all pre-existing tests plus ~26 new ones. Fix any failure before proceeding.

- [ ] **Step 2: Run the complete JS suite and production build**

```bash
npm test
npx jest
npm run production
git checkout -- package-lock.json
```

Expected: all pass, build succeeds. (`npm test` = vitest; `npx jest` covers the Jest suite CLAUDE.md mentions.)

- [ ] **Step 3: Manual smoke test of the setup flow (optional but recommended)**

If the Docker Compose stack is available: reset the dev DB, visit `http://localhost:8095`, confirm redirect to `/setup`, create the founder account, confirm landing on `/getting-started`, confirm `/register` shows the closed page afterwards.

- [ ] **Step 4: Push branch and open a PR**

```bash
git push -u origin feature/instance-gating
gh pr create --title "Instance gating, registration lockdown and first-run setup" --body "$(cat <<'EOF'
Implements the design in docs/superpowers/specs/2026-08-04-instance-gating-design.md:

- `PRODUCTION_ORGANISATION_IDS` env var: organisations not on the list get a persistent
  testing-mode banner in the Manage app pointing to self-hosting docs.
- Registration closes automatically once the first user exists; `REGISTRATION_OPEN=true`
  keeps it open (drinks.catlab.eu). Applies to local auth and first-time SSO logins.
- First-run setup page (`/setup`) on fresh instances: create founder account +
  organisation in one step.
- Friendly "Database not available" help page for connection/migration errors.
- Docs: "Run your own instance" walkthrough + env var reference; app.json entries;
  CLAUDE.md test-suite corrections.
- ~26 new PHPUnit tests riding the existing CI (MySQL matrix workflow).

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_01ETXGGmbDFTxnLdJrm1gjF5
EOF
)"
```

- [ ] **Step 5: Watch CI until green**

```bash
gh pr checks --watch
```

Expected: "Tests / PHP 8.1", "PHP 8.2", "PHP 8.3" and "Frontend Tests" all pass. If a job fails, read the log (`gh run view --log-failed`), fix, commit, push, and re-watch.
