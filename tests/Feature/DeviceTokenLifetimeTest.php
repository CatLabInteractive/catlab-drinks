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
use App\Models\DeviceAccessToken;
use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Organisations can configure how long POS device access tokens stay valid.
 */
class DeviceTokenLifetimeTest extends TestCase
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

	public function testOrganisationDefaultsToSevenDays(): void
	{
		$organisation = Organisation::factory()->create();

		$this->assertSame(7, $organisation->fresh()->device_token_lifetime_days);
	}

	public function testNewTokenUsesDefaultLifetime(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$device = Device::factory()->create(['organisation_id' => $organisation->id]);

		$token = $device->createAccessToken($user);

		$this->assertSame('2026-10-25 12:00:00', Carbon::parse($token->fresh()->expires_at)->format('Y-m-d H:i:s'));

		Carbon::setTestNow();
	}

	public function testNewTokenUsesOrganisationLifetime(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$organisation->device_token_lifetime_days = 30;
		$organisation->save();

		$device = Device::factory()->create(['organisation_id' => $organisation->id]);

		$token = $device->createAccessToken($user);

		$this->assertSame('2026-11-17 12:00:00', Carbon::parse($token->fresh()->expires_at)->format('Y-m-d H:i:s'));

		Carbon::setTestNow();
	}

	public function testExplicitLifetimeOverridesOrganisationSetting(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$organisation->device_token_lifetime_days = 30;
		$organisation->save();

		$device = Device::factory()->create(['organisation_id' => $organisation->id]);

		$token = $device->createAccessToken($user, 3600);

		$this->assertSame('2026-10-18 13:00:00', Carbon::parse($token->fresh()->expires_at)->format('Y-m-d H:i:s'));

		Carbon::setTestNow();
	}

	public function testChangingLifetimeDoesNotAffectExistingTokens(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$device = Device::factory()->create(['organisation_id' => $organisation->id]);
		$token = $device->createAccessToken($user);

		$organisation->device_token_lifetime_days = 1;
		$organisation->save();

		$this->assertSame('2026-10-25 12:00:00', Carbon::parse($token->fresh()->expires_at)->format('Y-m-d H:i:s'));

		Carbon::setTestNow();
	}

	public function testLifetimeIsExposedOnOrganisationResource(): void
	{
		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$organisation->device_token_lifetime_days = 14;
		$organisation->save();

		Passport::actingAs($user);
		$response = $this->getJson('/api/v1/organisations/' . $organisation->id);

		$response->assertStatus(200);
		$response->assertJsonPath('device_token_lifetime_days', 14);
	}

	public function testLifetimeCanBeUpdatedThroughApi(): void
	{
		$user = $this->makeUser();
		$organisation = $user->organisations()->first();

		Passport::actingAs($user);
		$response = $this->putJson('/api/v1/organisations/' . $organisation->id, [
			'name' => $organisation->name,
			'device_token_lifetime_days' => 90,
		]);

		$response->assertStatus(200);
		$response->assertJsonPath('device_token_lifetime_days', 90);
		$this->assertSame(90, $organisation->fresh()->device_token_lifetime_days);
	}

	public function testLifetimeBelowMinimumIsRejected(): void
	{
		$user = $this->makeUser();
		$organisation = $user->organisations()->first();

		Passport::actingAs($user);
		$response = $this->putJson('/api/v1/organisations/' . $organisation->id, [
			'name' => $organisation->name,
			'device_token_lifetime_days' => 0,
		]);

		$response->assertStatus(422);
		$this->assertSame(7, $organisation->fresh()->device_token_lifetime_days);
	}

	public function testLifetimeAboveMaximumIsRejected(): void
	{
		$user = $this->makeUser();
		$organisation = $user->organisations()->first();

		Passport::actingAs($user);
		$response = $this->putJson('/api/v1/organisations/' . $organisation->id, [
			'name' => $organisation->name,
			'device_token_lifetime_days' => 366,
		]);

		$response->assertStatus(422);
		$this->assertSame(7, $organisation->fresh()->device_token_lifetime_days);
	}

	public function testUnrelatedUserCannotUpdateLifetime(): void
	{
		$owner = $this->makeUser();
		$organisation = $owner->organisations()->first();

		$stranger = $this->makeUser();

		Passport::actingAs($stranger);
		$response = $this->putJson('/api/v1/organisations/' . $organisation->id, [
			'name' => $organisation->name,
			'device_token_lifetime_days' => 90,
		]);

		$response->assertStatus(403);
		$this->assertSame(7, $organisation->fresh()->device_token_lifetime_days);
	}

	public function testDeviceResourceExposesTokenExpiry(): void
	{
		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$device = Device::factory()->create(['organisation_id' => $organisation->id]);

		$token = new DeviceAccessToken([
			'device_id' => $device->id,
			'access_token' => 'test-access-token-' . Str::random(8),
			'expires_at' => Carbon::parse('2026-12-24 18:00:00'),
		]);
		$token->created_by = $user->id;
		$token->save();

		Passport::actingAs($user);
		$response = $this->getJson('/api/v1/devices/' . $device->id);

		$response->assertStatus(200);
		$this->assertSame(
			'2026-12-24 18:00:00',
			Carbon::parse($response->json('token_expires_at'))->format('Y-m-d H:i:s')
		);
	}

	public function testDeviceWithoutTokenHasNullExpiry(): void
	{
		$user = $this->makeUser();
		$organisation = $user->organisations()->first();
		$device = Device::factory()->create(['organisation_id' => $organisation->id]);

		Passport::actingAs($user);
		$response = $this->getJson('/api/v1/devices/' . $device->id);

		$response->assertStatus(200);
		$this->assertNull($response->json('token_expires_at'));
	}
}
