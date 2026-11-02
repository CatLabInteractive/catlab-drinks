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
 * Organisations carry an ISO 4217 currency code used to label every amount.
 *
 * The currency is changed with PATCH: Charon's PUT validates every field of the
 * resource, so a body carrying only the currency would fail on the required name.
 */
class OrganisationCurrencyTest extends TestCase
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

    public function testNewOrganisationDefaultsToEuro(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();
        $this->assertSame('EUR', $organisation->currency);

        Passport::actingAs($user);
        $this->getJson('/api/v1/users/me')
            ->assertStatus(200)
            ->assertJsonPath('organisations.items.0.currency', 'EUR');
    }

    public function testMemberCanChangeCurrency(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();

        Passport::actingAs($user);
        $this->patchJson('/api/v1/organisations/' . $organisation->id, ['currency' => 'USD'])
            ->assertStatus(200)
            ->assertJsonPath('currency', 'USD');
        $this->assertSame('USD', $organisation->fresh()->currency);
    }

    public function testInvalidCurrencyCodesAreRejected(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();
        Passport::actingAs($user);

        foreach (['usd', 'EURO', 'E€', '', ' USD'] as $code) {
            $this->patchJson('/api/v1/organisations/' . $organisation->id, ['currency' => $code])
                ->assertStatus(422);
            $this->assertSame('EUR', $organisation->fresh()->currency, "Code '{$code}' was stored");
        }
    }

    public function testLinkedOrganisationCanStillChangeCurrency(): void
    {
        $user = $this->makeUser();
        $organisation = $user->organisations()->first();
        $organisation->profile_id = 501;
        $organisation->save();

        Passport::actingAs($user);
        $this->patchJson('/api/v1/organisations/' . $organisation->id, ['currency' => 'GBP'])
            ->assertStatus(200);
        $this->assertSame('GBP', $organisation->fresh()->currency);
        $this->assertSame('Test User', $organisation->fresh()->name);
    }
}
