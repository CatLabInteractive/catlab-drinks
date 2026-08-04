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
