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
