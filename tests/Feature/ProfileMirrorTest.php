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

use App\Models\Organisation;
use App\Models\User;
use App\Services\ProfileMirror;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests the accounts profiles -> organisations mirror.
 * See docs/superpowers/specs/2026-08-04-profiles-organisations-sync-design.md
 */
class ProfileMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.catlab.url' => 'https://accounts.test/']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    public function testProfileSyncColumnsExist(): void
    {
        $this->assertTrue(Schema::hasColumns('organisations', ['profile_id', 'profile_sync_version']));
        $this->assertTrue(Schema::hasColumn('users', 'last_profile_sync'));
    }

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
}
