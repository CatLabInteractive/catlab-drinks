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

        Route::get('/pos-api/v1/_test/device-ping', function (\Illuminate\Http\Request $request) {
            return response((string)$request->header('Authorization'));
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

    public function testDeviceApiRequestWithGarbageBearerTokenDoesNotProbePassport(): void
    {
        Http::fake();

        $response = $this->withHeaders(['Authorization' => 'Bearer garbage-device-token'])
            ->get('/pos-api/v1/_test/device-ping');

        $response->assertStatus(200);
        // The middleware never touched the Passport guard, so the
        // Authorization header reaches the route handler untouched.
        $response->assertSee('Bearer garbage-device-token', false);
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
