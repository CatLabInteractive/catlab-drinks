# Accounts Profiles → Organisations Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mirror CatLab accounts "profiles" into local organisations (link, membership, name), triggered on SSO login and throttled on authenticated requests, with a Manage organisation switcher and a minimal delegated manage endpoint.

**Architecture:** A `ProfileMirror` service (port of `api.quizwitz.com/src/QuizWitz/Profiles/ProfileMirror.php`, minus licenses) pulls `GET /api/1.0/profiles` + `GET /api/1.0/profiles/{id}/members` from accounts with the user's stored bearer token and applies them to `organisations`/`organisation_user`. Triggers: the SSO login callback and a middleware appended to the `web`/`api` groups. Frontend gets a localStorage-backed organisation switcher; linked organisations become rename-only-on-accounts.

**Tech Stack:** Laravel 9 (PHP 8.1/8.2), Passport, Charon, Laravel `Http` client, Vue 3 compat + Bootstrap-Vue, vitest.

**Spec:** `docs/superpowers/specs/2026-08-04-profiles-organisations-sync-design.md` — read it first.

## Global Constraints

- Branch: work on `feature/profiles-organisations-sync` (already checked out).
- PHP tests: `vendor/bin/phpunit --filter <Class>` — needs the `catlab_drinks_test` MySQL DB (127.0.0.1:3306, user `test`/`test`). Run the full suite (`vendor/bin/phpunit`) before the final commit.
- `composer` commands need `--ignore-platform-reqs` on PHP > 8.2.
- **Never commit `package-lock.json`**; use `npm install` only, never `npm update`. Run `git checkout -- package-lock.json` before committing if it changed.
- JS tests: `npx vitest run` (new tests) and `npx jest` (must stay green).
- Accounts wire shapes (fixed, do not invent fields):
  - `GET {accounts}/api/1.0/profiles` → `{"items":[{"id":123,"name":"Team X","role":10,"personal":false,"version":7},…]}` (`version` may be absent on older accounts; a `debug` key may be present — ignore it).
  - `GET {accounts}/api/1.0/profiles/{id}/members` → `{"items":[{"userId":42,"role":10},…]}`; `userId` maps to `users.catlab_id`.
  - Delegated manage calls arrive with header `Authorization: Bearer: <client_secret>` (literal colon after `Bearer`).
- Accounts base URL: `config('services.catlab.url')` (default `https://accounts.catlab.eu/`, may have a trailing slash — always `rtrim(..., '/')`).
- Mirror constants: throttle 900 s, failure-retry 60 s, kill switch env `DISABLE_PROFILE_MIRROR`.
- Indentation: this codebase mixes tabs (models, resources/js) and 4-space (controllers, tests). Match the file you're editing.

---

### Task 1: Schema migration + model plumbing

**Files:**
- Create: `database/migrations/2026_08_04_100000_add_profile_sync_columns.php`
- Modify: `app/Models/User.php` (add `$casts`)
- Test: `tests/Feature/ProfileMirrorTest.php` (new file, first test)

**Interfaces:**
- Consumes: nothing.
- Produces: columns `organisations.profile_id` (unsignedBigInteger, nullable, unique), `organisations.profile_sync_version` (unsignedBigInteger, nullable), `users.last_profile_sync` (timestamp, nullable, cast to Carbon via `User::$casts`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProfileMirrorTest.php`:

```php
<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests the accounts profiles -> organisations mirror.
 * See docs/superpowers/specs/2026-08-04-profiles-organisations-sync-design.md
 */
class ProfileMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function testProfileSyncColumnsExist(): void
    {
        $this->assertTrue(Schema::hasColumns('organisations', ['profile_id', 'profile_sync_version']));
        $this->assertTrue(Schema::hasColumn('users', 'last_profile_sync'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: FAIL — `Failed asserting that false is true` (columns don't exist).

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_04_100000_add_profile_sync_columns.php` (match the anonymous-class style of `database/migrations/2026_03_03_103500_add_min_nfc_version_to_organisations.php` — if that file uses a named class, use a named class `AddProfileSyncColumns` instead):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links organisations to CatLab accounts "profiles" and adds the
 * incremental-sync bookkeeping columns for the ProfileMirror.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            // No FK: the profile lives on the accounts server.
            // The UNIQUE key is the backstop for the mirror's link races.
            $table->unsignedBigInteger('profile_id')->nullable()->unique()->after('name');
            // Last fully-applied accounts sync_version; NULL forces a full sync.
            $table->unsignedBigInteger('profile_sync_version')->nullable()->after('profile_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // Throttle / failure-backoff marker for the ProfileMirror.
            $table->timestamp('last_profile_sync')->nullable()->after('catlab_access_token');
        });
    }

    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['profile_id']);
            $table->dropColumn(['profile_id', 'profile_sync_version']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_profile_sync');
        });
    }
};
```

- [ ] **Step 4: Add the datetime cast to `app/Models/User.php`**

After the `$hidden` property (line ~79), add:

```php
	/**
	 * The attributes that should be cast.
	 *
	 * @var array
	 */
	protected $casts = [
		'last_profile_sync' => 'datetime',
	];
```

(This file uses tabs.)

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: PASS (RefreshDatabase runs the new migration).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_04_100000_add_profile_sync_columns.php app/Models/User.php tests/Feature/ProfileMirrorTest.php
git commit -m "Add profile link + sync bookkeeping columns"
```

---

### Task 2: ProfileMirror core — gatekeeping, list fetch, link/adopt/create, name reconcile

**Files:**
- Create: `app/Services/ProfileMirror.php`
- Modify: `config/services.php` (add `disable_profile_mirror` to the `catlab` block), `.env.example` (document `DISABLE_PROFILE_MIRROR`)
- Test: `tests/Feature/ProfileMirrorTest.php`

**Interfaces:**
- Consumes: Task 1 columns; `users.catlab_access_token`, `users.catlab_id`; `Organisation` model (`$fillable = ['name']` — `profile_id` is assigned directly, never mass-assigned).
- Produces: `App\Services\ProfileMirror` with public `sync(User $user, bool $force = false): void`, protected seams `linkProfile(Organisation $organisation, int $profileId): void`, `fetchItems(string $url, string $bearer): ?array`, `syncProfile(User $user, array $item, bool $force): bool`, constants `THROTTLE_SECONDS = 900`, `FAILURE_RETRY_SECONDS = 60`. Task 3 extends `syncProfile`; Task 4's race tests override `linkProfile`.

- [ ] **Step 1: Add test scaffolding + first failing tests**

In `tests/Feature/ProfileMirrorTest.php`, add imports and helpers (top of class):

```php
use App\Models\Organisation;
use App\Models\User;
use App\Services\ProfileMirror;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
```

```php
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.catlab.url' => 'https://accounts.test/']);
    }

    /**
     * Create a user as the SSO login flow would: catlab_id + access token.
     * The User::created hook auto-creates one (unlinked) organisation.
     */
    private function makeSsoUser(int $catlabId): User
    {
        $user = User::query()->create([
            'name' => 'User ' . $catlabId,
            'email' => 'user-' . $catlabId . '-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->catlab_id = $catlabId;
        $user->catlab_access_token = 'token-' . $catlabId;
        $user->save();

        return $user->fresh();
    }

    /**
     * Fake the accounts API. $membersByProfile maps profile id => members items.
     * Unmatched URLs 404 so no test ever hits the network.
     */
    private function fakeAccounts(array $profiles, array $membersByProfile = []): void
    {
        $responses = [];
        foreach ($membersByProfile as $profileId => $members) {
            $responses['https://accounts.test/api/1.0/profiles/' . $profileId . '/members'] =
                Http::response(['items' => $members]);
        }
        $responses['https://accounts.test/api/1.0/profiles'] = Http::response(['items' => $profiles]);
        $responses['*'] = Http::response('Not found', 404);

        Http::fake($responses);
    }
```

Add the test methods:

```php
    public function testPersonalProfileAdoptsAutoCreatedOrganisation(): void
    {
        $user = $this->makeSsoUser(1001);
        $autoOrg = $user->organisations()->first();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($user);

        $autoOrg->refresh();
        $this->assertSame(501, (int)$autoOrg->profile_id);
        $this->assertSame('Thijs', $autoOrg->name);
        $this->assertSame(1, Organisation::query()->count());
    }

    public function testSharedProfileCreatesNewOrganisation(): void
    {
        $user = $this->makeSsoUser(1001);

        $this->fakeAccounts(
            [
                ['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1],
                ['id' => 502, 'name' => 'Team Bar', 'role' => 1, 'personal' => false, 'version' => 3],
            ],
            [
                501 => [['userId' => 1001, 'role' => 10]],
                502 => [['userId' => 1001, 'role' => 1]],
            ]
        );

        (new ProfileMirror())->sync($user);

        $teamOrg = Organisation::query()->where('profile_id', 502)->first();
        $this->assertNotNull($teamOrg);
        $this->assertSame('Team Bar', $teamOrg->name);
        $this->assertTrue($teamOrg->users()->whereKey($user->id)->exists());
        $this->assertSame(2, Organisation::query()->count());
    }

    public function testExistingLinkIsReusedAndRenamed(): void
    {
        $user = $this->makeSsoUser(1001);
        $org = $user->organisations()->first();
        $org->profile_id = 501;
        $org->save();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Renamed on accounts', 'role' => 10, 'personal' => true, 'version' => 2]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($user);

        $this->assertSame(1, Organisation::query()->count());
        $this->assertSame('Renamed on accounts', $org->fresh()->name);
    }

    public function testKillSwitchBlocksEvenForcedSync(): void
    {
        config(['services.catlab.disable_profile_mirror' => true]);
        $user = $this->makeSsoUser(1001);
        Http::fake();

        (new ProfileMirror())->sync($user, true);

        Http::assertNothingSent();
        $this->assertNull($user->organisations()->first()->profile_id);
    }

    public function testNoTokenMeansNoSync(): void
    {
        $user = $this->makeSsoUser(1001);
        $user->catlab_access_token = null;
        $user->save();
        Http::fake();

        (new ProfileMirror())->sync($user->fresh());

        Http::assertNothingSent();
    }

    public function testFailedListFetchChangesNothing(): void
    {
        $user = $this->makeSsoUser(1001);
        Http::fake(['*' => Http::response('Server error', 500)]);

        (new ProfileMirror())->sync($user);

        $this->assertNull($user->organisations()->first()->profile_id);
        // Backoff was stamped so the next attempt is throttled.
        $this->assertNotNull($user->fresh()->last_profile_sync);
    }

    public function testThrottleSkipsSecondUnforcedSync(): void
    {
        $user = $this->makeSsoUser(1001);
        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        $mirror = new ProfileMirror();
        $mirror->sync($user);
        $sentAfterFirst = count(Http::recorded());

        $mirror->sync($user->fresh());

        $this->assertGreaterThan(0, $sentAfterFirst);
        $this->assertCount($sentAfterFirst, Http::recorded());
    }

    public function testForceBypassesThrottle(): void
    {
        $user = $this->makeSsoUser(1001);
        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        $mirror = new ProfileMirror();
        $mirror->sync($user);
        $sentAfterFirst = count(Http::recorded());

        $mirror->sync($user->fresh(), true);

        $this->assertGreaterThan($sentAfterFirst, count(Http::recorded()));
    }
```

Note: in this task the members endpoint is never called (roster sync arrives in Task 3); the members fakes are already in place so these tests keep passing unchanged after Task 3.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: FAIL — `Class "App\Services\ProfileMirror" not found`.

- [ ] **Step 3: Implement `app/Services/ProfileMirror.php`**

Create the file (4-space indentation, GPL header comment like other `app/` files):

```php
<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Services;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors CatLab accounts "profiles" into local organisations.
 *
 * Port of the QuizWitz ProfileMirror (list + roster only; drinks has no
 * licenses). Pull-based: runs on SSO login and throttled on authenticated
 * requests, using the user's stored accounts access token.
 *
 * See docs/superpowers/specs/2026-08-04-profiles-organisations-sync-design.md
 */
class ProfileMirror
{
    /**
     * Minimum seconds between unforced syncs per user.
     */
    const THROTTLE_SECONDS = 900;

    /**
     * After a failed sync, retry opens after this many seconds instead of
     * hammering accounts on every request during an outage.
     */
    const FAILURE_RETRY_SECONDS = 60;

    /**
     * Sync all of the user's accounts profiles into local organisations.
     * Never throws for transport-level problems; callers should still wrap
     * calls in try/catch so an unexpected error never breaks auth.
     *
     * @param User $user
     * @param bool $force Skip the throttle and the per-profile version skip guard.
     */
    public function sync(User $user, bool $force = false): void
    {
        if (config('services.catlab.disable_profile_mirror')) {
            return;
        }

        if (empty($user->catlab_access_token)) {
            return;
        }

        if ($force) {
            $this->stampSync($user);
        } elseif (!$this->claimSync($user)) {
            return;
        }

        $items = $this->fetchItems($this->getProfilesUrl(), $user->catlab_access_token);

        if ($items === null) {
            Log::warning('ProfileMirror: could not fetch profiles list', ['user' => $user->id]);
            $this->stampFailureBackoff($user);
            return;
        }

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }

            if (!$this->syncProfile($user, $item, $force)) {
                $this->stampFailureBackoff($user);
                return;
            }
        }

        $this->stampSync($user);

        // Authorization reads the cached relation; make sure checks later in
        // this request see the fresh membership.
        $user->unsetRelation('organisations');
    }

    /**
     * Sync a single profile list item into an organisation.
     * @param User $user
     * @param array $item ['id' =>, 'name' =>, 'role' =>, 'personal' =>, 'version' =>?]
     * @param bool $force
     * @return bool false aborts the remaining sync and triggers failure backoff.
     */
    protected function syncProfile(User $user, array $item, bool $force): bool
    {
        $profileId = (int)$item['id'];
        $name = trim((string)($item['name'] ?? ''));

        $organisation = Organisation::query()->where('profile_id', $profileId)->first();
        $created = false;

        if (!$organisation && !empty($item['personal'])) {
            $organisation = $this->adoptPersonalOrganisation($user, $profileId);
        }

        if (!$organisation) {
            [$organisation, $created] = $this->createOrganisation($user, $profileId, $name);
        }

        if (!$organisation) {
            // Lost a link race and could not resolve the winner; retry later.
            return false;
        }

        // The accounts name is canonical.
        if ($name !== '' && $organisation->name !== $name) {
            $organisation->name = $name;
            $organisation->save();
        }

        return true;
    }

    /**
     * Adopt the oldest unlinked organisation this user belongs to (the
     * User::created hook guarantees one exists for pre-profiles users).
     * @return Organisation|null
     */
    protected function adoptPersonalOrganisation(User $user, int $profileId)
    {
        $candidate = $user->organisations()
            ->whereNull('profile_id')
            ->orderBy('organisations.id')
            ->first();

        if (!$candidate) {
            return null;
        }

        try {
            $this->linkProfile($candidate, $profileId);
            return $candidate;
        } catch (QueryException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            // A concurrent request linked this profile first; use the winner.
            return Organisation::query()->where('profile_id', $profileId)->first();
        }
    }

    /**
     * Create a new organisation for a profile and link it.
     * @return array [Organisation|null, bool $created]
     */
    protected function createOrganisation(User $user, int $profileId, string $name)
    {
        $organisation = new Organisation([
            'name' => $name !== '' ? $name : 'Organisation ' . $profileId,
        ]);
        $organisation->save();
        $organisation->users()->attach($user->id);

        try {
            $this->linkProfile($organisation, $profileId);
            return [$organisation, true];
        } catch (QueryException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            // Lost the creation race: remove the husk (it has no content yet)
            // and adopt the winner.
            $organisation->users()->detach();
            $organisation->delete();

            return [Organisation::query()->where('profile_id', $profileId)->first(), false];
        }
    }

    /**
     * Link an organisation to an accounts profile. Separate seam so the
     * race-condition tests can interpose; throws QueryException (1062) when
     * another organisation already holds the profile_id.
     */
    protected function linkProfile(Organisation $organisation, int $profileId): void
    {
        $organisation->profile_id = $profileId;
        $organisation->save();
    }

    /**
     * @param QueryException $e
     * @return bool true when the exception is a MySQL duplicate-key (1062).
     */
    protected function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /**
     * GET an accounts collection endpoint. Returns the items array on a
     * well-formed 200, null on any transport error / non-200 / bad body
     * (the uniform abort signal).
     * @return array|null
     */
    protected function fetchItems(string $url, string $bearer)
    {
        try {
            $response = Http::withToken($bearer)
                ->timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url);
        } catch (\Exception $e) {
            return null;
        }

        if ($response->status() !== 200) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return null;
        }

        return $data['items'];
    }

    /**
     * Claim the right to sync with one atomic conditional update; only the
     * winner proceeds. The in-memory pre-check makes steady-state requests
     * free (no extra query).
     */
    protected function claimSync(User $user): bool
    {
        $last = $user->last_profile_sync;
        if ($last && $last->gt(now()->subSeconds(self::THROTTLE_SECONDS))) {
            return false;
        }

        $claimed = User::query()
            ->whereKey($user->id)
            ->where(function ($query) {
                $query->whereNull('last_profile_sync')
                    ->orWhere('last_profile_sync', '<', now()->subSeconds(self::THROTTLE_SECONDS));
            })
            ->update(['last_profile_sync' => now()]);

        if ($claimed > 0) {
            $user->last_profile_sync = now();
            return true;
        }

        return false;
    }

    protected function stampSync(User $user): void
    {
        $user->last_profile_sync = now();
        User::query()->whereKey($user->id)->update(['last_profile_sync' => now()]);
    }

    /**
     * Stamp so that the next unforced sync is allowed FAILURE_RETRY_SECONDS
     * from now instead of THROTTLE_SECONDS.
     */
    protected function stampFailureBackoff(User $user): void
    {
        $stamp = now()->subSeconds(self::THROTTLE_SECONDS - self::FAILURE_RETRY_SECONDS);
        $user->last_profile_sync = $stamp;
        User::query()->whereKey($user->id)->update(['last_profile_sync' => $stamp]);
    }

    protected function getBaseUrl(): string
    {
        return rtrim((string)config('services.catlab.url'), '/');
    }

    protected function getProfilesUrl(): string
    {
        return $this->getBaseUrl() . '/api/1.0/profiles';
    }

    protected function getMembersUrl(int $profileId): string
    {
        return $this->getBaseUrl() . '/api/1.0/profiles/' . $profileId . '/members';
    }
}
```

- [ ] **Step 4: Add the kill-switch config**

In `config/services.php`, inside the `'catlab'` array (after `'model'`):

```php
        'disable_profile_mirror' => env('DISABLE_PROFILE_MIRROR', false),
```

In `.env.example`, after the `CATLAB_CLIENT_SECRET=` line:

```
# Emergency stop for the accounts profiles -> organisations sync.
DISABLE_PROFILE_MIRROR=
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: PASS (all 9 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/ProfileMirror.php config/services.php .env.example tests/Feature/ProfileMirrorTest.php
git commit -m "ProfileMirror core: link/adopt/create organisations from accounts profiles"
```

---

### Task 3: Roster sync, version stamping, skip guard

**Files:**
- Modify: `app/Services/ProfileMirror.php` (`syncProfile` + new methods)
- Test: `tests/Feature/ProfileMirrorTest.php`

**Interfaces:**
- Consumes: Task 2's `ProfileMirror` (seams `fetchItems`, `getMembersUrl`).
- Produces: full membership sync + `profile_sync_version` stamping; skip guard semantics later tasks rely on. No signature changes.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ProfileMirrorTest.php`:

```php
    public function testRosterAddsOtherLocalMembers(): void
    {
        $userA = $this->makeSsoUser(1001);
        $userB = $this->makeSsoUser(1002);

        $this->fakeAccounts(
            [
                ['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1],
                ['id' => 502, 'name' => 'Team Bar', 'role' => 1, 'personal' => false, 'version' => 3],
            ],
            [
                501 => [['userId' => 1001, 'role' => 10]],
                502 => [['userId' => 1001, 'role' => 1], ['userId' => 1002, 'role' => 10]],
            ]
        );

        (new ProfileMirror())->sync($userA);

        $teamOrg = Organisation::query()->where('profile_id', 502)->first();
        $this->assertTrue($teamOrg->users()->whereKey($userA->id)->exists());
        $this->assertTrue($teamOrg->users()->whereKey($userB->id)->exists());
        $this->assertSame(3, (int)$teamOrg->profile_sync_version);
    }

    public function testRosterRemovesDepartedMembers(): void
    {
        $userA = $this->makeSsoUser(1001);
        $userB = $this->makeSsoUser(1002);

        $org = $userA->organisations()->first();
        $org->profile_id = 501;
        $org->save();
        $org->users()->attach($userB->id);

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 5]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($userA);

        $this->assertTrue($org->users()->whereKey($userA->id)->exists());
        $this->assertFalse($org->users()->whereKey($userB->id)->exists());
    }

    public function testUnknownRosterMembersAreSkipped(): void
    {
        $user = $this->makeSsoUser(1001);

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1]],
            [501 => [['userId' => 1001, 'role' => 10], ['userId' => 9999, 'role' => 1]]]
        );

        (new ProfileMirror())->sync($user);

        $org = Organisation::query()->where('profile_id', 501)->first();
        $this->assertSame(1, $org->users()->count());
    }

    public function testSkipGuardSkipsRosterFetchButStillRenames(): void
    {
        $user = $this->makeSsoUser(1001);
        $org = $user->organisations()->first();
        $org->profile_id = 501;
        $org->profile_sync_version = 7;
        $org->name = 'Stale name';
        $org->save();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Fresh name', 'role' => 10, 'personal' => true, 'version' => 7]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($user);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/members');
        });
        $this->assertSame('Fresh name', $org->fresh()->name);
    }

    public function testSkipGuardIgnoredWhenVersionDiffers(): void
    {
        $user = $this->makeSsoUser(1001);
        $org = $user->organisations()->first();
        $org->profile_id = 501;
        $org->profile_sync_version = 7;
        $org->save();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 8]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($user);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/members');
        });
        $this->assertSame(8, (int)$org->fresh()->profile_sync_version);
    }

    public function testSkipGuardIgnoredWhenUserIsNotLocalMember(): void
    {
        // A member added on accounts before their own first login: the org
        // exists at the right version but they are not a local member yet.
        $owner = $this->makeSsoUser(1001);
        $org = $owner->organisations()->first();
        $org->profile_id = 502;
        $org->profile_sync_version = 3;
        $org->save();

        $newcomer = $this->makeSsoUser(1002);

        $this->fakeAccounts(
            [
                ['id' => 601, 'name' => 'Newcomer', 'role' => 10, 'personal' => true, 'version' => 1],
                ['id' => 502, 'name' => 'Team Bar', 'role' => 1, 'personal' => false, 'version' => 3],
            ],
            [
                601 => [['userId' => 1002, 'role' => 10]],
                502 => [['userId' => 1001, 'role' => 10], ['userId' => 1002, 'role' => 1]],
            ]
        );

        (new ProfileMirror())->sync($newcomer);

        $this->assertTrue($org->users()->whereKey($newcomer->id)->exists());
    }

    public function testForceBypassesSkipGuard(): void
    {
        $user = $this->makeSsoUser(1001);
        $org = $user->organisations()->first();
        $org->profile_id = 501;
        $org->profile_sync_version = 7;
        $org->save();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 7]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($user, true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/members');
        });
    }

    public function testMissingVersionAlwaysFullSyncs(): void
    {
        $user = $this->makeSsoUser(1001);
        $org = $user->organisations()->first();
        $org->profile_id = 501;
        $org->profile_sync_version = 7;
        $org->save();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        (new ProfileMirror())->sync($user);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/members');
        });
        // Stored version tracks what accounts last said - including "nothing".
        $this->assertNull($org->fresh()->profile_sync_version);
    }

    public function testFailedRosterFetchKeepsMembershipAndVersion(): void
    {
        $userA = $this->makeSsoUser(1001);
        $userB = $this->makeSsoUser(1002);
        $org = $userA->organisations()->first();
        $org->profile_id = 501;
        $org->profile_sync_version = 4;
        $org->save();
        $org->users()->attach($userB->id);

        Http::fake([
            'https://accounts.test/api/1.0/profiles/501/members' => Http::response('Server error', 500),
            'https://accounts.test/api/1.0/profiles' => Http::response([
                'items' => [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 9]],
            ]),
            '*' => Http::response('Not found', 404),
        ]);

        (new ProfileMirror())->sync($userA);

        $this->assertSame(2, $org->users()->count());
        $this->assertSame(4, (int)$org->fresh()->profile_sync_version);
    }
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: the 9 new tests FAIL (no members fetch / no version stamping yet); the Task 2 tests still PASS.

- [ ] **Step 3: Extend `syncProfile` with roster + version logic**

In `app/Services/ProfileMirror.php`, add `use Illuminate\Support\Facades\DB;` to the imports, then replace the end of `syncProfile()` — everything from the "accounts name is canonical" block to `return true;` — with:

```php
        // The accounts name is canonical.
        if ($name !== '' && $organisation->name !== $name) {
            $organisation->name = $name;
            $organisation->save();
        }

        // Incremental-sync skip guard: nothing changed on accounts since the
        // stored version AND this user is already a local member (a member
        // added accounts-side before their own first login must not be
        // skipped: their membership only lands when they sync themselves).
        $version = array_key_exists('version', $item) ? (int)$item['version'] : null;

        if (!$force && !$created && $version !== null
            && $organisation->profile_sync_version !== null
            && (int)$organisation->profile_sync_version === $version
            && $organisation->users()->whereKey($user->id)->exists()) {
            return true;
        }

        // Roster. A failed fetch must never wipe membership.
        $members = $this->fetchItems($this->getMembersUrl($profileId), $user->catlab_access_token);
        if ($members === null) {
            Log::warning('ProfileMirror: could not fetch members', [
                'user' => $user->id,
                'profile' => $profileId,
            ]);
            return false;
        }

        $catlabIds = [];
        foreach ($members as $member) {
            if (is_array($member) && isset($member['userId'])) {
                $catlabIds[] = (int)$member['userId'];
            }
        }

        // Unknown members are skipped; they are picked up at their own first
        // login (guaranteed by the skip guard's membership term above).
        $localUserIds = User::query()
            ->whereIn('catlab_id', $catlabIds)
            ->pluck('id')
            ->all();

        // Apply + stamp serialized per organisation: last-to-apply is also
        // last-to-stamp, so a stale applier stamps its own stale version and
        // the next sync self-heals.
        DB::transaction(function () use ($organisation, $localUserIds, $version) {
            $locked = Organisation::query()
                ->whereKey($organisation->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return;
            }

            $locked->users()->sync($localUserIds);
            $locked->profile_sync_version = $version;
            $locked->save();
        });

        $organisation->unsetRelation('users');

        return true;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: PASS (18 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ProfileMirror.php tests/Feature/ProfileMirrorTest.php
git commit -m "ProfileMirror: roster sync with sync_version skip guard"
```

---

### Task 4: Race recovery and failure backoff timing

**Files:**
- Modify: `app/Services/ProfileMirror.php` (no changes expected — this task *proves* the Task 2 race paths and backoff timing work; fix them if the tests expose bugs)
- Test: `tests/Feature/ProfileMirrorTest.php`

**Interfaces:**
- Consumes: `ProfileMirror::linkProfile()` seam from Task 2.
- Produces: verified race + backoff behaviour.

- [ ] **Step 1: Write the failing/verifying tests**

Add to `tests/Feature/ProfileMirrorTest.php` (plus `use Illuminate\Support\Carbon;` in the imports and a `tearDown` that clears the test clock):

```php
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A ProfileMirror whose first linkProfile call loses the race: a
     * concurrent "winner" appears just before the link, so the UNIQUE key
     * on organisations.profile_id fires for real.
     */
    private function makeRacingMirror(): ProfileMirror
    {
        return new class extends ProfileMirror {
            public $raced = false;

            protected function linkProfile(\App\Models\Organisation $organisation, int $profileId): void
            {
                if (!$this->raced) {
                    $this->raced = true;
                    $winner = new \App\Models\Organisation(['name' => 'Winner ' . $profileId]);
                    $winner->save();
                    parent::linkProfile($winner, $profileId);
                }
                parent::linkProfile($organisation, $profileId);
            }
        };
    }

    public function testAdoptionRaceLoserUsesWinner(): void
    {
        $user = $this->makeSsoUser(1001);
        $autoOrg = $user->organisations()->first();

        $this->fakeAccounts(
            [['id' => 501, 'name' => 'Thijs', 'role' => 10, 'personal' => true, 'version' => 1]],
            [501 => [['userId' => 1001, 'role' => 10]]]
        );

        $this->makeRacingMirror()->sync($user);

        $winner = Organisation::query()->where('profile_id', 501)->first();
        $this->assertNotSame($autoOrg->id, $winner->id);
        $this->assertTrue($winner->users()->whereKey($user->id)->exists());
        // The candidate stays unlinked (a later profile could adopt it).
        $this->assertNull($autoOrg->fresh()->profile_id);
    }

    public function testCreationRaceLoserDeletesHuskAndAdoptsWinner(): void
    {
        $user = $this->makeSsoUser(1001);
        // Link the personal profile up front so the shared profile goes
        // through the creation path.
        $autoOrg = $user->organisations()->first();
        $autoOrg->profile_id = 601;
        $autoOrg->profile_sync_version = 1;
        $autoOrg->save();

        $this->fakeAccounts(
            [
                ['id' => 601, 'name' => $autoOrg->name, 'role' => 10, 'personal' => true, 'version' => 1],
                ['id' => 502, 'name' => 'Team Bar', 'role' => 1, 'personal' => false, 'version' => 3],
            ],
            [
                601 => [['userId' => 1001, 'role' => 10]],
                502 => [['userId' => 1001, 'role' => 1]],
            ]
        );

        $this->makeRacingMirror()->sync($user);

        $winner = Organisation::query()->where('profile_id', 502)->first();
        $this->assertSame('Team Bar', $winner->name);
        $this->assertTrue($winner->users()->whereKey($user->id)->exists());
        // The husk is gone: only the personal org and the winner remain.
        $this->assertSame(2, Organisation::query()->count());
    }

    public function testFailureBackoffReopensAfterRetryWindow(): void
    {
        $user = $this->makeSsoUser(1001);
        Http::fake(['*' => Http::response('Server error', 500)]);

        $mirror = new ProfileMirror();
        $mirror->sync($user);
        $this->assertCount(1, Http::recorded());

        // Still inside the 60s retry window: throttled.
        $mirror->sync($user->fresh());
        $this->assertCount(1, Http::recorded());

        // After the retry window the next sync goes out again.
        Carbon::setTestNow(now()->addSeconds(ProfileMirror::FAILURE_RETRY_SECONDS + 1));
        $mirror->sync($user->fresh());
        $this->assertCount(2, Http::recorded());
    }
```

- [ ] **Step 2: Run tests**

Run: `vendor/bin/phpunit --filter ProfileMirrorTest`
Expected: PASS if Task 2's race handling is correct. If any fail, fix `ProfileMirror` (not the tests): the adoption catch must re-fetch the winner; the creation catch must detach + delete the husk before adopting; `stampFailureBackoff` must stamp `now − (THROTTLE_SECONDS − FAILURE_RETRY_SECONDS)`.

Note: `testAdoptionRaceLoserUsesWinner` exercises a real MySQL 1062 — if it errors with a closed transaction or non-QueryException, check that `linkProfile` runs outside any transaction (it must).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ProfileMirrorTest.php app/Services/ProfileMirror.php
git commit -m "ProfileMirror: pin race recovery and failure backoff behaviour"
```

---

### Task 5: Sync triggers — SSO login hook + middleware

**Files:**
- Modify: `app/Http/Controllers/Auth/SsoLoginController.php`
- Create: `app/Http/Middleware/SyncAccountsProfiles.php`
- Modify: `app/Http/Kernel.php` (append to `web` and `api` groups)
- Test: `tests/Feature/ProfileSyncTriggerTest.php` (new)

**Interfaces:**
- Consumes: `App\Services\ProfileMirror::sync(User $user, bool $force = false)`.
- Produces: automatic syncing; no new public API.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ProfileSyncTriggerTest.php` (same GPL header as other tests):

```php
<?php
/* (GPL header as in other test files) */

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

/**
 * Tests that the ProfileMirror runs on SSO login and (throttled) on
 * authenticated web/api requests.
 */
class ProfileSyncTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.catlab.url' => 'https://accounts.test/']);

        Route::get('/_test/sso-callback', [
            \App\Http\Controllers\Auth\SsoLoginController::class,
            'postLogin',
        ])->middleware('web');

        Route::get('/_test/web-ping', function () {
            return response('pong');
        })->middleware('web');

        Route::get('/_test/api-ping', function () {
            return response('pong');
        })->middleware('api');
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

    private function fakeAccountsPersonalProfile(int $catlabId, int $profileId): void
    {
        Http::fake([
            'https://accounts.test/api/1.0/profiles/' . $profileId . '/members' => Http::response([
                'items' => [['userId' => $catlabId, 'role' => 10]],
            ]),
            'https://accounts.test/api/1.0/profiles' => Http::response([
                'items' => [
                    ['id' => $profileId, 'name' => 'Synced Name', 'role' => 10, 'personal' => true, 'version' => 1],
                ],
            ]),
            '*' => Http::response('Not found', 404),
        ]);
    }

    private function makeSsoUser(int $catlabId): User
    {
        $user = User::query()->create([
            'name' => 'User ' . $catlabId,
            'email' => 'user-' . $catlabId . '-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->catlab_id = $catlabId;
        $user->catlab_access_token = 'token-' . $catlabId;
        $user->save();

        return $user->fresh();
    }

    public function testSsoLoginSyncsProfiles(): void
    {
        config(['instance.registration_open' => true]);
        $this->fakeAccountsPersonalProfile(123456, 501);
        $this->mockSocialiteUser('123456');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $user = User::query()->where('catlab_id', '123456')->first();
        $org = $user->organisations()->first();
        $this->assertSame(501, (int)$org->profile_id);
        $this->assertSame('Synced Name', $org->name);
    }

    public function testSsoLoginSucceedsWhenAccountsIsDown(): void
    {
        config(['instance.registration_open' => true]);
        Http::fake(['*' => Http::response('Server error', 500)]);
        $this->mockSocialiteUser('123457');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertAuthenticated();
    }

    public function testAuthenticatedWebRequestTriggersThrottledSync(): void
    {
        $user = $this->makeSsoUser(1001);
        $this->fakeAccountsPersonalProfile(1001, 501);

        $this->actingAs($user)->get('/_test/web-ping')->assertStatus(200);

        $this->assertSame(501, (int)$user->organisations()->first()->fresh()->profile_id);
        $sentAfterFirst = count(Http::recorded());

        // Second request inside the throttle window: no extra calls.
        $this->actingAs($user->fresh())->get('/_test/web-ping')->assertStatus(200);
        $this->assertCount($sentAfterFirst, Http::recorded());
    }

    public function testAuthenticatedApiRequestTriggersSync(): void
    {
        $user = $this->makeSsoUser(1002);
        $this->fakeAccountsPersonalProfile(1002, 502);

        Passport::actingAs($user);
        $this->get('/_test/api-ping')->assertStatus(200);

        $this->assertSame(502, (int)$user->organisations()->first()->fresh()->profile_id);
    }

    public function testGuestRequestDoesNotSync(): void
    {
        Http::fake();

        $this->get('/_test/web-ping')->assertStatus(200);

        Http::assertNothingSent();
    }

    public function testUserWithoutTokenDoesNotSync(): void
    {
        $user = User::query()->create([
            'name' => 'Local User',
            'email' => 'local-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        Http::fake();

        $this->actingAs($user)->get('/_test/web-ping')->assertStatus(200);

        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter ProfileSyncTriggerTest`
Expected: `testSsoLoginSyncsProfiles`, `testAuthenticatedWebRequestTriggersThrottledSync`, `testAuthenticatedApiRequestTriggersSync` FAIL (no sync happens); the negative tests may already pass.

- [ ] **Step 3: Hook the SSO login controller**

In `app/Http/Controllers/Auth/SsoLoginController.php`, add imports:

```php
use App\Services\ProfileMirror;
use Illuminate\Support\Facades\Log;
```

and change `getUserFromSocialite` to:

```php
    protected function getUserFromSocialite($socialiteUser)
    {
        $existing = $this->getUserFromCatLabId($socialiteUser->getId());

        if (!$existing && !InstanceSettings::isRegistrationOpen()) {
            throw new HttpResponseException(
                response()->view('auth.registration-closed', [], 403)
            );
        }

        $user = parent::getUserFromSocialite($socialiteUser);

        // Mirror accounts profiles into organisations. Never fail the login
        // over a sync problem.
        try {
            app(ProfileMirror::class)->sync($user);
        } catch (\Throwable $e) {
            Log::warning('ProfileMirror: sync failed during SSO login', [
                'user' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
```

- [ ] **Step 4: Create the middleware and register it**

Create `app/Http/Middleware/SyncAccountsProfiles.php` (GPL header as in other middleware):

```php
<?php
/* (GPL header) */

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ProfileMirror;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Opportunistically re-syncs accounts profiles for authenticated users.
 * Throttled inside ProfileMirror (15 min); a failure never breaks a request.
 */
class SyncAccountsProfiles
{
    public function handle(Request $request, Closure $next)
    {
        $user = $this->resolveUser($request);

        if ($user instanceof User && !empty($user->catlab_access_token)) {
            try {
                app(ProfileMirror::class)->sync($user);
            } catch (\Throwable $e) {
                Log::warning('ProfileMirror: sync failed in middleware', [
                    'user' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $next($request);
    }

    /**
     * Session guard for web requests, Passport guard for API requests.
     * Device principals resolve to null and fall through.
     * @return mixed
     */
    private function resolveUser(Request $request)
    {
        if ($request->hasSession() && $request->user()) {
            return $request->user();
        }

        return $request->user('api');
    }
}
```

In `app/Http/Kernel.php`, append to both groups:

```php
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Laravel\Passport\Http\Middleware\CreateFreshApiToken::class,
            \App\Http\Middleware\SyncAccountsProfiles::class,
        ],

        'api' => [
            //'throttle:60,1',
            'bindings',
            \App\Http\Middleware\SyncAccountsProfiles::class,
        ],
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter "ProfileSyncTriggerTest|SsoRegistrationGateTest"`
Expected: PASS — including the pre-existing SSO gate tests (they mock no HTTP; the new sync call must not break them; if they fail because the mirror now fires, check that the socialite fake token leads to a fetch failure → backoff, which is tolerated).

Note: `SsoRegistrationGateTest` users get `catlab_access_token = 'fake-token'`, so the login hook will attempt one HTTP call. Add `Http::fake(['*' => Http::response('Not found', 404)]);` to that test's `setUp()` to keep it network-free — modify `tests/Feature/SsoRegistrationGateTest.php` accordingly (add the `Illuminate\Support\Facades\Http` import).

- [ ] **Step 6: Run the full suite to catch fallout**

Run: `vendor/bin/phpunit`
Expected: PASS. The middleware runs on every web/api request in every feature test; users without `catlab_access_token` no-op, so nothing else should break. If a test fails with stray HTTP requests, that test's user has a token — add an `Http::fake` there.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Auth/SsoLoginController.php app/Http/Middleware/SyncAccountsProfiles.php app/Http/Kernel.php tests/Feature/ProfileSyncTriggerTest.php tests/Feature/SsoRegistrationGateTest.php
git commit -m "Trigger profile sync on SSO login and throttled on authenticated requests"
```

---

### Task 6: Delegated manage endpoint

**Files:**
- Create: `app/Http/Controllers/DelegatedManageController.php`, `app/Http/Middleware/AuthenticateAccountsManageCall.php`
- Modify: `routes/web.php`, `app/Http/Kernel.php` (`$routeMiddleware`), `app/Http/Middleware/VerifyCsrfToken.php` (`$except`)
- Test: `tests/Feature/DelegatedManageTest.php` (new)

**Interfaces:**
- Consumes: `users.catlab_id`, Passport's `oauth_access_tokens`/`oauth_refresh_tokens` tables.
- Produces: `POST /delegated/users` — the URL to register as `manage_user_uri` on the accounts OAuth client.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DelegatedManageTest.php`:

```php
<?php
/* (GPL header) */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests the delegated management endpoint called by the accounts server.
 */
class DelegatedManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.catlab.client_secret' => 'shared-secret-123']);
    }

    private function makeSsoUser(int $catlabId): User
    {
        $user = User::query()->create([
            'name' => 'User ' . $catlabId,
            'email' => 'user-' . $catlabId . '-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->catlab_id = $catlabId;
        $user->save();

        return $user->fresh();
    }

    private function insertAccessToken(User $user): string
    {
        $id = 'test-token-' . Str::random(40);
        DB::table('oauth_access_tokens')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'client_id' => 1,
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function call(array $body)
    {
        return $this->postJson('/delegated/users', $body, [
            'Authorization' => 'Bearer: shared-secret-123',
        ]);
    }

    public function testRejectsWrongSecret(): void
    {
        $response = $this->postJson('/delegated/users', ['user_id' => 1, 'action' => 'logout'], [
            'Authorization' => 'Bearer: wrong-secret',
        ]);

        $response->assertStatus(400);
    }

    public function testRejectsWhenNoSecretConfigured(): void
    {
        config(['services.catlab.client_secret' => null]);

        $response = $this->postJson('/delegated/users', ['user_id' => 1, 'action' => 'logout'], [
            'Authorization' => 'Bearer: ',
        ]);

        $response->assertStatus(400);
    }

    public function testUnknownUserReturns404(): void
    {
        $this->call(['user_id' => 999999, 'action' => 'delete'])->assertStatus(404);
    }

    public function testDeleteRemovesUserAndMembershipsButKeepsOrganisations(): void
    {
        $user = $this->makeSsoUser(4001);
        $organisation = $user->organisations()->first();
        $tokenId = $this->insertAccessToken($user);

        $this->call(['user_id' => 4001, 'action' => 'delete'])->assertStatus(200);

        $this->assertNull(User::query()->find($user->id));
        $this->assertSame(0, DB::table('organisation_user')->where('user_id', $user->id)->count());
        $this->assertNotNull($organisation->fresh());
        $this->assertSame(1, (int)DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));
    }

    public function testLogoutRevokesTokensButKeepsUser(): void
    {
        $user = $this->makeSsoUser(4002);
        $tokenId = $this->insertAccessToken($user);

        $this->call(['user_id' => 4002, 'action' => 'logout'])->assertStatus(200);

        $this->assertNotNull(User::query()->find($user->id));
        $this->assertSame(1, (int)DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));
    }

    public function testUnsupportedUserActionIsANoOp(): void
    {
        $this->makeSsoUser(4003);

        $this->call(['user_id' => 4003, 'action' => 'activity'])->assertStatus(200);
    }

    public function testProfileActionsAreNotSupported(): void
    {
        $response = $this->call(['profile_id' => 501, 'action' => 'synchronize-orders']);

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'not supported for profile_id',
            $response->json('error.message')
        );
    }

    public function testMissingIdsRejected(): void
    {
        $this->call(['action' => 'delete'])->assertStatus(400);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter DelegatedManageTest`
Expected: FAIL — 404s on `/delegated/users` (route missing).

- [ ] **Step 3: Implement middleware, controller, route**

Create `app/Http/Middleware/AuthenticateAccountsManageCall.php`:

```php
<?php
/* (GPL header) */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Authenticates server-to-server calls from the CatLab accounts server.
 * Accounts sends: Authorization: Bearer: <client_secret> (literal colon).
 */
class AuthenticateAccountsManageCall
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string)config('services.catlab.client_secret');
        $header = (string)$request->header('Authorization');

        if ($secret === '' || !hash_equals('Bearer: ' . $secret, $header)) {
            return response()->json([
                'error' => ['message' => 'Invalid authentication.'],
            ], 400);
        }

        return $next($request);
    }
}
```

Create `app/Http/Controllers/DelegatedManageController.php`:

```php
<?php
/* (GPL header) */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Delegated management endpoint for the CatLab accounts server
 * (the manage_user_uri configured on the drinks OAuth client).
 *
 * Contract: unknown user => 404 (accounts reads that as "already removed");
 * unsupported user actions => 200 no-op so accounts flows never break;
 * profile actions are not supported here.
 */
class DelegatedManageController
{
    public function manage(Request $request)
    {
        if ($request->input('user_id')) {
            return $this->manageUser($request);
        }

        if ($request->input('profile_id')) {
            return response()->json([
                'error' => [
                    'message' => sprintf(
                        'Action %s is not supported for profile_id',
                        $request->input('action')
                    ),
                ],
            ], 400);
        }

        return response()->json([
            'error' => ['message' => 'user_id or profile_id is required.'],
        ], 400);
    }

    private function manageUser(Request $request)
    {
        $user = User::query()
            ->where('catlab_id', $request->input('user_id'))
            ->first();

        if (!$user) {
            return response()->json([
                'error' => ['message' => 'User not found.'],
            ], 404);
        }

        switch ($request->input('action')) {
            case 'delete':
                $this->revokeTokens($user);
                $user->organisations()->detach();
                $user->delete();
                return response()->json(['success' => true]);

            case 'logout':
                $this->revokeTokens($user);
                return response()->json(['success' => true]);

            default:
                // Drinks tracks nothing for other actions (info, activity, ...).
                return response()->json([]);
        }
    }

    private function revokeTokens(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');
        if ($tokenIds->isEmpty()) {
            return;
        }

        DB::table('oauth_refresh_tokens')
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);

        $user->tokens()->update(['revoked' => true]);
    }
}
```

In `app/Http/Kernel.php`, add to `$routeMiddleware` (alphabetical position, after `'auth.basic'`):

```php
        'accounts.manage' => \App\Http\Middleware\AuthenticateAccountsManageCall::class,
```

In `routes/web.php`, at the bottom:

```php
/*
 * Delegated management callbacks from the CatLab accounts server
 * (register as manage_user_uri on the accounts OAuth client).
 */
Route::post('/delegated/users', [\App\Http\Controllers\DelegatedManageController::class, 'manage'])
    ->middleware('accounts.manage');
```

In `app/Http/Middleware/VerifyCsrfToken.php`:

```php
    protected $except = [
        'delegated/*',
    ];
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter DelegatedManageTest`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DelegatedManageController.php app/Http/Middleware/AuthenticateAccountsManageCall.php app/Http/Kernel.php app/Http/Middleware/VerifyCsrfToken.php routes/web.php tests/Feature/DelegatedManageTest.php
git commit -m "Add delegated manage endpoint for accounts (user delete/logout)"
```

---

### Task 7: Read-only name for linked organisations + expose profile_id

**Files:**
- Modify: `app/Http/ManagementApi/V1/Controllers/OrganisationController.php`, `app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php`
- Test: `tests/Feature/OrganisationNameGuardTest.php` (new)

**Interfaces:**
- Consumes: `organisations.profile_id` (Task 1).
- Produces: `profile_id` visible (read-only) on the organisation resource — the frontend (Task 10) uses it to decide whether to show the "Rename on CatLab Accounts" link.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/OrganisationNameGuardTest.php`:

```php
<?php
/* (GPL header) */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * A profile-linked organisation's name is managed on accounts and must not
 * be editable through the drinks API.
 */
class OrganisationNameGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function testLinkedOrganisationNameIsReadOnly(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();
        $organisation->profile_id = 501;
        $organisation->save();

        Passport::actingAs($user);
        $response = $this->putJson('/api/v1/organisations/' . $organisation->id, [
            'name' => 'Hacked locally',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Test User', $organisation->fresh()->name);
    }

    public function testUnlinkedOrganisationCanStillBeRenamed(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();

        Passport::actingAs($user);
        $response = $this->putJson('/api/v1/organisations/' . $organisation->id, [
            'name' => 'New local name',
        ]);

        $response->assertStatus(200);
        $this->assertSame('New local name', $organisation->fresh()->name);
    }

    public function testProfileIdIsExposedOnUsersMe(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();
        $organisation->profile_id = 501;
        $organisation->save();

        Passport::actingAs($user);
        $response = $this->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJsonPath('organisations.items.0.profile_id', 501);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter OrganisationNameGuardTest`
Expected: `testLinkedOrganisationNameIsReadOnly` FAIL (rename succeeds), `testProfileIdIsExposedOnUsersMe` FAIL (`profile_id` missing). `testUnlinkedOrganisationCanStillBeRenamed` should already pass — if it fails, the Charon PUT contract differs; inspect the actual response and adjust the request body (not the production code) accordingly.

- [ ] **Step 3: Implement the guard and the field**

In `app/Http/ManagementApi/V1/Controllers/OrganisationController.php`, replace `beforeSaveEntity`:

```php
    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);

        // Profile-linked organisations are renamed on the accounts server;
        // the mirror would overwrite any local rename on the next sync.
        if (!$isNew && $entity->profile_id !== null && $entity->isDirty('name')) {
            abort(422, 'This organisation\'s name is managed on CatLab Accounts.');
        }

        return $entity;
    }
```

In `app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php`, after the `name` field:

```php
        $this->field('profile_id')
            ->int()
            ->visible(true)
            ->writeable(false, false);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter "OrganisationNameGuardTest|OrganisationTestModeTest"`
Expected: PASS (the existing test-mode tests also exercise the organisations expansion — they must stay green).

- [ ] **Step 5: Commit**

```bash
git add app/Http/ManagementApi/V1/Controllers/OrganisationController.php app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php tests/Feature/OrganisationNameGuardTest.php
git commit -m "Linked organisations: name read-only, expose profile_id"
```

---

### Task 8: Backend multi-org call sites

**Files:**
- Modify: `app/Http/Controllers/ClientController.php`, `resources/views/client/manage.blade.php`, `app/Http/Controllers/LicenseController.php`
- Test: `tests/Feature/MultiOrgBackendTest.php` (new)

**Interfaces:**
- Consumes: existing `Device` model/factory; `users->organisations()` many-to-many.
- Produces: `/manage` tolerates zero organisations; license application works for devices in any of the user's organisations; blade also exposes `window.CATLAB_DRINKS_CONFIG.ACCOUNTS_URL` (used by Task 10).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MultiOrgBackendTest.php`:

```php
<?php
/* (GPL header) */

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Backend call sites that assumed exactly one organisation per user.
 */
class MultiOrgBackendTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function testManagePageRendersForUserWithoutOrganisations(): void
    {
        $user = $this->makeUser();
        $user->organisations()->detach();

        $response = $this->actingAs($user)->get('/manage');

        $response->assertStatus(200);
    }

    public function testLicenseAppliesToDeviceInSecondOrganisation(): void
    {
        $user = $this->makeUser();
        $secondOrg = Organisation::factory()->create();
        $user->organisations()->attach($secondOrg->id);

        $device = Device::factory()->create(['organisation_id' => $secondOrg->id]);

        $licenseKey = base64_encode(json_encode([
            'data' => ['device_uid' => $device->uid],
            'signature' => 'test-signature',
        ]));

        $response = $this->actingAs($user)->get('/manage/devices/apply-license?' . http_build_query([
            'device_id' => $device->id,
            'license' => $licenseKey,
        ]));

        $response->assertRedirect('/manage/devices');
        $this->assertSame($licenseKey, $device->fresh()->license_key);
    }

    public function testLicenseNotAppliedToForeignDevice(): void
    {
        $user = $this->makeUser();
        $foreignOrg = Organisation::factory()->create();
        $device = Device::factory()->create(['organisation_id' => $foreignOrg->id]);

        $licenseKey = base64_encode(json_encode([
            'data' => ['device_uid' => $device->uid],
            'signature' => 'test-signature',
        ]));

        $response = $this->actingAs($user)->get('/manage/devices/apply-license?' . http_build_query([
            'device_id' => $device->id,
            'license' => $licenseKey,
        ]));

        $response->assertRedirect('/manage/devices');
        $this->assertNull($device->fresh()->license_key);
    }
}
```

If `Device::factory()` lacks defaults for required fields, inspect `database/factories/DeviceFactory.php` and pass the missing attributes explicitly in the test.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter MultiOrgBackendTest`
Expected: `testManagePageRendersForUserWithoutOrganisations` FAIL (blade calls `$organisation->id` on null → 500), `testLicenseAppliesToDeviceInSecondOrganisation` FAIL (device lookup scoped to first org only).

- [ ] **Step 3: Fix the call sites**

`resources/views/client/manage.blade.php` — replace the inline script block:

```html
<script>
    var ORGANISATION_ID = {{ $organisation ? $organisation->id : 'null' }};
    window.CATLAB_DRINKS_CONFIG = window.CATLAB_DRINKS_CONFIG || {};
    window.CATLAB_DRINKS_CONFIG.ACCOUNTS_URL = @json(rtrim((string)config('services.catlab.url'), '/'));
</script>
```

(`ORGANISATION_ID` is a pre-boot placeholder only; `app.js` overwrites it from `users/me` during boot — Task 10.)

`app/Http/Controllers/LicenseController.php` — in `applyLicense`, replace the organisation lookup + device query:

```php
        $organisationIds = \Auth::user()->organisations()->pluck('organisations.id');
        if ($organisationIds->isEmpty()) {
            return redirect('/manage/devices');
        }

        $device = Device::where('id', $deviceId)
            ->whereIn('organisation_id', $organisationIds)
            ->first();
```

`app/Http/Controllers/ClientController.php` — no change needed (`first()` may return null; the blade now guards). Verify nothing else in the view depends on `$organisation`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter MultiOrgBackendTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/client/manage.blade.php app/Http/Controllers/LicenseController.php tests/Feature/MultiOrgBackendTest.php
git commit -m "Multi-org: tolerate zero orgs on /manage, license lookup across all user orgs"
```

---

### Task 9: Organisation selection helper (frontend) + vitest

**Files:**
- Create: `resources/manage/js/services/organisationSelection.js`
- Test: `resources/manage/js/services/organisationSelection.test.js`

**Interfaces:**
- Consumes: nothing.
- Produces: `export const ORGANISATION_STORAGE_KEY = 'catlab_drinks_manage_organisation_id'` and `export function selectOrganisation(items, storedId)` returning the matching item, the first item, or `null`. Task 10 imports both.

- [ ] **Step 1: Write the failing test**

Create `resources/manage/js/services/organisationSelection.test.js`:

```js
import { describe, expect, it } from 'vitest';
import { selectOrganisation } from './organisationSelection';

describe('selectOrganisation', () => {
	const items = [
		{ id: 1, name: 'Personal' },
		{ id: 2, name: 'Team' },
	];

	it('returns the first organisation when nothing is stored', () => {
		expect(selectOrganisation(items, null)).toEqual(items[0]);
	});

	it('returns the stored organisation when present', () => {
		expect(selectOrganisation(items, '2')).toEqual(items[1]);
	});

	it('ignores a stale stored id', () => {
		expect(selectOrganisation(items, '999')).toEqual(items[0]);
	});

	it('returns null for an empty or missing list', () => {
		expect(selectOrganisation([], null)).toBeNull();
		expect(selectOrganisation(null, '1')).toBeNull();
	});
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run resources/manage/js/services/organisationSelection.test.js`
Expected: FAIL — cannot resolve `./organisationSelection`.

- [ ] **Step 3: Implement the helper**

Create `resources/manage/js/services/organisationSelection.js` (tabs, GPL header like other resources/js files):

```js
/* (GPL header) */

/**
 * localStorage key holding the id of the organisation the user last
 * selected in the Manage app.
 */
export const ORGANISATION_STORAGE_KEY = 'catlab_drinks_manage_organisation_id';

/**
 * Pick the active organisation at boot: the stored selection when it is
 * still in the user's list, otherwise the first organisation.
 *
 * @param {Array|null} items organisations from GET /api/v1/users/me
 * @param {string|null} storedId value from localStorage
 * @returns {Object|null}
 */
export function selectOrganisation(items, storedId) {
	if (!items || items.length === 0) {
		return null;
	}

	if (storedId !== null && storedId !== undefined) {
		const stored = items.find((item) => String(item.id) === String(storedId));
		if (stored) {
			return stored;
		}
	}

	return items[0];
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run` and `npx jest`
Expected: both PASS (jest must not pick up the new file — its roots are `tests/js`).

- [ ] **Step 5: Commit**

```bash
git add resources/manage/js/services/organisationSelection.js resources/manage/js/services/organisationSelection.test.js
git commit -m "Add organisation selection helper for the Manage boot flow"
```

---

### Task 10: Manage boot integration, navbar switcher, settings link, docs

**Files:**
- Modify: `resources/manage/js/app.js`, `resources/manage/js/views/App.vue`, `resources/manage/js/views/OrganisationSettings.vue`, `resources/shared/js/i18n/nl.js` (and the other i18n files if they list every key), `CLAUDE.md`
- Test: manual build + existing suites (no new automated tests; the selection logic was tested in Task 9)

**Interfaces:**
- Consumes: `selectOrganisation` / `ORGANISATION_STORAGE_KEY` (Task 9); `profile_id` on organisation items (Task 7); `window.CATLAB_DRINKS_CONFIG.ACCOUNTS_URL` (Task 8).
- Produces: `window.ORGANISATIONS` (the full items array) — new global for the switcher and settings view.

- [ ] **Step 1: Wire the boot flow in `resources/manage/js/app.js`**

Add to the imports (after the `OrganisationService` import):

```js
import {ORGANISATION_STORAGE_KEY, selectOrganisation} from './services/organisationSelection';
```

Replace the two `window.ORGANISATION_*` lines inside the `users/me` `.then()` callback:

```js
					return axios.get('/api/v1/users/me')
						.then(response => {
							const organisations = response.data.organisations.items;
							const organisation = selectOrganisation(
								organisations,
								window.localStorage.getItem(ORGANISATION_STORAGE_KEY)
							);

							window.ORGANISATIONS = organisations;
							window.ORGANISATION_ID = organisation ? organisation.id : null;
							window.ORGANISATION_TEST_MODE = organisation ? !!organisation.test_mode : false;
```

(the rest of the callback — CardService setup — stays unchanged).

- [ ] **Step 2: Add the switcher to `resources/manage/js/views/App.vue`**

In the template, inside the right-aligned `<b-navbar-nav>` before the Settings dropdown:

```html
					<b-nav-item-dropdown v-if="organisations.length > 1" :text="currentOrganisationName" right>
						<b-dropdown-item
							v-for="organisation in organisations"
							:key="organisation.id"
							:active="organisation.id === currentOrganisationId"
							@click="switchOrganisation(organisation)"
						>{{ organisation.name }}</b-dropdown-item>
					</b-nav-item-dropdown>
```

In the script block:

```js
	import LogoutLink from '../components/LogoutLink.vue';
	import LanguageToggle from '../../../shared/js/components/LanguageToggle.vue';
	import { ORGANISATION_STORAGE_KEY } from '../services/organisationSelection';

	export default {

		components: {
			'logout-link': LogoutLink,
			'language-toggle': LanguageToggle,
		},

		data() {
			return {
				kioskMode: false,
				testMode: !!window.ORGANISATION_TEST_MODE,
				organisations: window.ORGANISATIONS || [],
				currentOrganisationId: window.ORGANISATION_ID
			}
		},

		computed: {
			currentOrganisationName() {
				const current = this.organisations.find(
					(organisation) => organisation.id === this.currentOrganisationId
				);
				return current ? current.name : '';
			}
		},

		methods: {
			switchOrganisation(organisation) {
				window.localStorage.setItem(ORGANISATION_STORAGE_KEY, String(organisation.id));
				window.location.reload();
			}
		},

		unmounted() {
			this.eventListeners.forEach(e => e.unbind());
		},

		mounted() {
			this.eventListeners = [];
		}
	}
```

- [ ] **Step 3: Organisation block in `resources/manage/js/views/OrganisationSettings.vue`**

In the template, right after the `<h1>` line (before the loading spinner):

```html
		<p v-if="organisationName">
			<strong>{{ organisationName }}</strong>
			<template v-if="profileId">
				&mdash;
				<a :href="accountsProfileUrl" target="_blank" rel="noopener">{{ $t('Rename on CatLab Accounts') }}</a>
			</template>
		</p>
```

In the script's `data()`, derive the current organisation:

```js
			const currentOrganisation = (window.ORGANISATIONS || []).find(
				(organisation) => organisation.id === window.ORGANISATION_ID
			) || null;
```

and add to the returned object:

```js
				organisationName: currentOrganisation ? currentOrganisation.name : '',
				profileId: currentOrganisation ? currentOrganisation.profile_id : null,
```

Add a computed:

```js
			accountsProfileUrl() {
				return (window.CATLAB_DRINKS_CONFIG.ACCOUNTS_URL || 'https://accounts.catlab.eu')
					+ '/profiles/' + this.profileId;
			},
```

(if the component has no `computed` block yet, add one; if it has, extend it).

- [ ] **Step 4: i18n**

Open `resources/shared/js/i18n/nl.js` and add translations following the existing key style:

- `'Rename on CatLab Accounts'` → `'Naam wijzigen op CatLab Accounts'`

Check `fr.js`, `de.js`, `es.js`, `en.js`: add the key only where the files enumerate all keys (missing keys fall back to the English key text — follow whatever convention the existing files show).

- [ ] **Step 5: Build + run all test suites**

```bash
npm install
npm run dev
npx vitest run
npx jest
vendor/bin/phpunit
git checkout -- package-lock.json
```

Expected: the webpack build succeeds and all three suites PASS.

- [ ] **Step 6: Update CLAUDE.md**

Add to `CLAUDE.md` — a new subsection under "Instance Configuration" (or a sibling section "Accounts profile sync"):

```markdown
## Accounts Profile Sync (SSO instances)

- `App\Services\ProfileMirror` mirrors CatLab accounts "profiles" into local
  organisations: link via `organisations.profile_id` (unique), membership via
  the roster endpoint, incremental skip via `organisations.profile_sync_version`
  vs the accounts `version`. Names of linked organisations are canonical on
  accounts and read-only in the drinks API.
- Triggers: SSO login (`SsoLoginController`) + `SyncAccountsProfiles`
  middleware on the `web`/`api` groups (throttled 15 min per user via
  `users.last_profile_sync`; failure backoff 60 s). Kill switch:
  `DISABLE_PROFILE_MIRROR=1`.
- `POST /delegated/users` (secret-authenticated, `accounts.manage` middleware)
  handles accounts-initiated user `delete`/`logout`; register it as
  `manage_user_uri` on the accounts OAuth client.
- Manage supports multiple organisations: boot picks the org stored in
  localStorage (`catlab_drinks_manage_organisation_id`) via
  `resources/manage/js/services/organisationSelection.js`; a navbar dropdown
  switches (stores + reloads). `window.ORGANISATIONS` holds the full list.
- Design: `docs/superpowers/specs/2026-08-04-profiles-organisations-sync-design.md`.
```

Also update the Key Models table row for Organisation if it mentions columns, and add `DISABLE_PROFILE_MIRROR` anywhere env vars are listed (the "Instance Configuration" section).

- [ ] **Step 7: Commit**

```bash
git add resources/manage/js/app.js resources/manage/js/views/App.vue resources/manage/js/views/OrganisationSettings.vue resources/shared/js/i18n/ CLAUDE.md
git commit -m "Manage: organisation switcher, accounts rename link, profile sync docs"
```

---

### Task 11: Final verification

**Files:** none new.

- [ ] **Step 1: Full test run**

```bash
vendor/bin/phpunit
npx vitest run
npx jest
npm run dev
git checkout -- package-lock.json
git status
```

Expected: all suites PASS, build succeeds, working tree clean except intended changes.

- [ ] **Step 2: Route sanity**

```bash
php artisan route:list --path=delegated
```

Expected: `POST delegated/users` listed with `accounts.manage` middleware.

- [ ] **Step 3: Review the diff against the spec**

Skim `git log --oneline main..HEAD` and `git diff main --stat`; confirm every spec section (schema, mirror, triggers, switcher, read-only name, delegated endpoint, tests, config) is present. Fix anything missing before hand-off.
