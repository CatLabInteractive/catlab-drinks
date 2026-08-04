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
