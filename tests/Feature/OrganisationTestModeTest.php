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
