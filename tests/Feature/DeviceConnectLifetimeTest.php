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
use App\Models\DeviceConnectRequest;
use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The access token lifetime can be chosen per connect request, at the time a
 * device is authenticated. It defaults to the organisation setting.
 */
class DeviceConnectLifetimeTest extends TestCase
{
	use RefreshDatabase;

	private User $user;
	private Organisation $organisation;

	protected function setUp(): void
	{
		parent::setUp();

		$this->user = User::query()->create([
			'name' => 'Test User',
			'email' => 'test-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$this->organisation = $this->user->organisations()->first();
		$this->organisation->device_token_lifetime_days = 14;
		$this->organisation->save();
	}

	protected function tearDown(): void
	{
		Carbon::setTestNow();
		parent::tearDown();
	}

	private function createConnectRequest(array $params = [])
	{
		Passport::actingAs($this->user);
		$url = '/api/v1/organisations/' . $this->organisation->id . '/device-connect-requests';
		if ($params) {
			$url .= '?' . http_build_query($params);
		}
		return $this->postJson($url);
	}

	private function tokenExpiry(string $accessToken): string
	{
		$token = DeviceAccessToken::where('access_token', $accessToken)->firstOrFail();
		return Carbon::parse($token->expires_at)->format('Y-m-d H:i:s');
	}

	public function testConnectRequestDefaultsToOrganisationLifetime(): void
	{
		$response = $this->createConnectRequest();

		$response->assertStatus(200);
		$response->assertJsonPath('token_lifetime_days', 14);
	}

	public function testConnectRequestAcceptsLifetime(): void
	{
		$response = $this->createConnectRequest(['token_lifetime_days' => 30]);

		$response->assertStatus(200);
		$response->assertJsonPath('token_lifetime_days', 30);

		$this->assertSame(30, DeviceConnectRequest::where('token', $response->json('token'))->first()->token_lifetime_days);
	}

	public function testConnectRequestRejectsLifetimeOutOfRange(): void
	{
		$this->createConnectRequest(['token_lifetime_days' => 0])->assertStatus(422);
		$this->createConnectRequest(['token_lifetime_days' => 366])->assertStatus(422);
		$this->createConnectRequest(['token_lifetime_days' => 'abc'])->assertStatus(422);
	}

	public function testPingUpdatesLifetime(): void
	{
		$token = $this->createConnectRequest()->json('token');

		$response = $this->postJson('/api/v1/device-connect-requests/' . $token . '/ping?token_lifetime_days=3');

		$response->assertStatus(200);
		$response->assertJsonPath('token_lifetime_days', 3);

		// A ping without the parameter leaves it alone.
		$response = $this->postJson('/api/v1/device-connect-requests/' . $token . '/ping');
		$response->assertJsonPath('token_lifetime_days', 3);
	}

	public function testPingRejectsLifetimeOutOfRange(): void
	{
		$token = $this->createConnectRequest()->json('token');

		$this->postJson('/api/v1/device-connect-requests/' . $token . '/ping?token_lifetime_days=400')
			->assertStatus(422);

		$this->assertSame(14, DeviceConnectRequest::where('token', $token)->first()->token_lifetime_days);
	}

	public function testKnownDeviceReceivesTokenWithRequestedLifetime(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$device = Device::factory()->create(['organisation_id' => $this->organisation->id]);
		$token = $this->createConnectRequest(['token_lifetime_days' => 2])->json('token');

		// The device claims the request (unauthenticated endpoint).
		$this->app['auth']->forgetGuards();
		$response = $this->postJson('/api/v1/device-connect', [
			'token' => $token,
			'device_uid' => $device->uid,
		]);

		$response->assertStatus(200);
		$accessToken = $response->json('access_token');
		$this->assertNotEmpty($accessToken);
		$this->assertSame('2026-10-20 12:00:00', $this->tokenExpiry($accessToken));
	}

	public function testKnownDeviceFallsBackToOrganisationLifetime(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$device = Device::factory()->create(['organisation_id' => $this->organisation->id]);
		$token = $this->createConnectRequest()->json('token');

		$this->app['auth']->forgetGuards();
		$response = $this->postJson('/api/v1/device-connect', [
			'token' => $token,
			'device_uid' => $device->uid,
		]);

		$response->assertStatus(200);
		$this->assertSame('2026-11-01 12:00:00', $this->tokenExpiry($response->json('access_token')));
	}

	public function testNewDevicePairingUsesLifetimeChosenAtPairing(): void
	{
		Carbon::setTestNow('2026-10-18 12:00:00');

		$token = $this->createConnectRequest()->json('token');

		// 1. New device claims without a uid and receives a pairing code.
		$this->app['auth']->forgetGuards();
		$claim = $this->postJson('/api/v1/device-connect', ['token' => $token]);
		$claim->assertStatus(200);
		$pairingCode = $claim->json('pairing_code');
		$deviceUid = $claim->json('device_uid');
		$this->assertNotEmpty($pairingCode);
		$this->assertNotEmpty($deviceUid);

		// 2. Admin pairs and picks a lifetime of 5 days.
		Passport::actingAs($this->user);
		$pair = $this->postJson('/api/v1/device-connect-requests/' . $token . '/pair?token_lifetime_days=5', [
			'pairing_code' => $pairingCode,
			'device_name' => 'Bar Terminal 1',
		]);
		$pair->assertStatus(200);
		$pair->assertJsonPath('token_lifetime_days', 5);

		// 3. Device claims again and receives its access token.
		$this->app['auth']->forgetGuards();
		$claim = $this->postJson('/api/v1/device-connect', [
			'token' => $token,
			'device_uid' => $deviceUid,
		]);
		$claim->assertStatus(200);
		$this->assertSame('2026-10-23 12:00:00', $this->tokenExpiry($claim->json('access_token')));
	}
}
