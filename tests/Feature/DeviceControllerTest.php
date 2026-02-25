<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeviceControllerTest extends TestCase
{
	use RefreshDatabase;

	private Organisation $organisation;
	private Device $device;
	private User $user;
	private DeviceAccessToken $token;

	protected function setUp(): void
	{
		parent::setUp();

		$this->organisation = Organisation::factory()->create();

		$this->device = Device::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);

		$this->user = User::query()->create([
			'name' => 'Test User',
			'email' => 'test-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);

		$this->organisation->users()->attach($this->user->id);

		$this->token = new DeviceAccessToken([
			'device_id' => $this->device->id,
			'access_token' => 'test-access-token-' . Str::random(8),
			'expires_at' => now()->addHour(),
		]);
		$this->token->created_by = $this->user->id;
		$this->token->save();
	}

	// ─── Management API: POST /api/v1/devices/{id}/approve-key ───

	public function testApproveKeySucceeds(): void
	{
		$this->device->public_key = 'test-public-key-data';
		$this->device->save();

		Passport::actingAs($this->user);
		$response = $this
			->postJson('/api/v1/devices/' . $this->device->id . '/approve-key');

		$response->assertStatus(200);

		$this->device->refresh();
		$this->assertNotNull($this->device->approved_at);
		$this->assertEquals($this->user->id, $this->device->approved_by);
	}

	public function testApproveKeyFailsWithoutPublicKey(): void
	{
		Passport::actingAs($this->user);
		$response = $this
			->postJson('/api/v1/devices/' . $this->device->id . '/approve-key');

		$response->assertStatus(422);
		$response->assertJsonFragment([
			'message' => 'Device has no public key to approve.',
		]);
	}

	public function testApproveKeyForbiddenForUnrelatedUser(): void
	{
		$otherUser = User::query()->create([
			'name' => 'Other User',
			'email' => 'other-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);

		$this->device->public_key = 'test-public-key-data';
		$this->device->save();

		Passport::actingAs($otherUser);
		$response = $this
			->postJson('/api/v1/devices/' . $this->device->id . '/approve-key');

		$response->assertStatus(403);
	}

	// ─── Management API: POST /api/v1/devices/{id}/revoke-key ───

	public function testRevokeKeySucceeds(): void
	{
		$this->device->public_key = 'test-public-key-data';
		$this->device->approved_at = now();
		$this->device->approved_by = $this->user->id;
		$this->device->save();

		Passport::actingAs($this->user);
		$response = $this
			->postJson('/api/v1/devices/' . $this->device->id . '/revoke-key');

		$response->assertStatus(200);

		$this->device->refresh();
		$this->assertNull($this->device->public_key);
		$this->assertNull($this->device->approved_at);
		$this->assertNull($this->device->approved_by);
	}

	public function testRevokeKeyForbiddenForUnrelatedUser(): void
	{
		$otherUser = User::query()->create([
			'name' => 'Other User',
			'email' => 'other-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);

		Passport::actingAs($otherUser);
		$response = $this
			->postJson('/api/v1/devices/' . $this->device->id . '/revoke-key');

		$response->assertStatus(403);
	}

	// ─── Management API: GET /api/v1/organisations/{id}/public-keys ───

	public function testPublicKeysListsDevicesWithKeys(): void
	{
		$this->device->public_key = 'test-public-key-data';
		$this->device->save();

		$deviceWithoutKey = Device::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);

		Passport::actingAs($this->user);
		$response = $this
			->getJson('/api/v1/organisations/' . $this->organisation->id . '/public-keys');

		$response->assertStatus(200);

		$items = $response->json('items') ?? $response->json();
		$ids = collect(is_array($items) ? $items : [])->pluck('id')->toArray();
		$this->assertContains($this->device->id, $ids);
		$this->assertNotContains($deviceWithoutKey->id, $ids);
	}

	public function testPublicKeysIncludesSoftDeletedDevices(): void
	{
		$this->device->public_key = 'test-public-key-data';
		$this->device->save();
		$this->device->delete(); // soft delete

		Passport::actingAs($this->user);
		$response = $this
			->getJson('/api/v1/organisations/' . $this->organisation->id . '/public-keys');

		$response->assertStatus(200);

		$items = $response->json('items') ?? $response->json();
		$ids = collect(is_array($items) ? $items : [])->pluck('id')->toArray();
		$this->assertContains($this->device->id, $ids);
	}

	// ─── Management API: GET /api/v1/devices/{id}/signed-cards ───

	public function testSignedCardsListsCards(): void
	{
		$card = new Card();
		$card->uid = 'test-card-uid-' . Str::random(8);
		$card->name = 'Test Card';
		$card->organisation_id = $this->organisation->id;
		$card->transaction_count = 0;
		$card->last_signing_device_id = $this->device->id;
		$card->save();

		Passport::actingAs($this->user);
		$response = $this
			->getJson('/api/v1/devices/' . $this->device->id . '/signed-cards');

		$response->assertStatus(200);
	}

	// ─── Device API: GET /pos-api/v1/devices/current ───

	public function testCurrentDeviceReturnsDeviceInfo(): void
	{
		$response = $this
			->withHeader('Authorization', 'Bearer ' . $this->token->access_token)
			->getJson('/pos-api/v1/devices/current');

		$response->assertStatus(200);
		$response->assertJsonFragment([
			'id' => $this->device->id,
		]);
	}

	// ─── Device API: PUT /pos-api/v1/devices/current ───

	public function testUpdateCurrentDevicePublicKey(): void
	{
		$response = $this
			->withHeader('Authorization', 'Bearer ' . $this->token->access_token)
			->putJson('/pos-api/v1/devices/current', [
				'public_key' => 'new-test-public-key',
			]);

		$response->assertStatus(200);

		$this->device->refresh();
		$this->assertEquals('new-test-public-key', $this->device->public_key);
	}

	public function testUpdateCurrentDeviceRejectsUnauthenticated(): void
	{
		$response = $this
			->withHeader('Authorization', 'Bearer invalid-token')
			->putJson('/pos-api/v1/devices/current', [
				'public_key' => 'new-test-public-key',
			]);

		$response->assertStatus(401);
	}

	// ─── Device API: GET /pos-api/v1/organisations/{id}/approved-public-keys ───

	public function testApprovedPublicKeysReturnsOnlyApproved(): void
	{
		$approvedDevice = Device::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);
		$approvedDevice->public_key = 'approved-key';
		$approvedDevice->approved_at = now();
		$approvedDevice->approved_by = $this->user->id;
		$approvedDevice->save();

		$unapprovedDevice = Device::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);
		$unapprovedDevice->public_key = 'unapproved-key';
		$unapprovedDevice->save();

		$response = $this
			->withHeader('Authorization', 'Bearer ' . $this->token->access_token)
			->getJson('/pos-api/v1/organisations/' . $this->organisation->id . '/approved-public-keys');

		$response->assertStatus(200);

		$items = $response->json('items') ?? $response->json();
		$ids = collect(is_array($items) ? $items : [])->pluck('id')->toArray();
		$this->assertContains($approvedDevice->id, $ids);
		$this->assertNotContains($unapprovedDevice->id, $ids);
	}
}
