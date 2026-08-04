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
