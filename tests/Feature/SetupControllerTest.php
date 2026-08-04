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
