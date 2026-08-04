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

        $this->mockSocialiteUser('123456');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(403);
        $this->assertNull(User::query()->where('catlab_id', '123456')->first());
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

        $this->mockSocialiteUser('456789');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertNotNull(User::query()->where('catlab_id', '456789')->first());
    }

    public function testFirstSsoUserAcceptedOnEmptyInstance(): void
    {
        config(['instance.registration_open' => false]);

        $this->mockSocialiteUser('789012');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertNotNull(User::query()->where('catlab_id', '789012')->first());
    }

    public function testExistingSsoUserStillLogsInWhenClosed(): void
    {
        $existing = User::query()->create([
            'name' => 'SSO Veteran',
            'email' => 'veteran-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $existing->catlab_id = '999888';
        $existing->save();

        config(['instance.registration_open' => false]);

        $this->mockSocialiteUser('999888');

        $response = $this->get('/_test/sso-callback');

        $response->assertStatus(302);
        $this->assertAuthenticated();
    }
}
