<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Card;
use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\DeviceConnectRequest;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Organisation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Regression tests for resource scoping between organisations and for
 * input handling on the public order endpoint.
 */
class AccessScopingTest extends TestCase
{
	use RefreshDatabase;

	private Organisation $organisation;
	private Organisation $otherOrganisation;
	private User $user;

	protected function setUp(): void
	{
		parent::setUp();

		$this->organisation = Organisation::factory()->create();
		$this->otherOrganisation = Organisation::factory()->create();

		$this->user = User::query()->create([
			'name' => 'Member',
			'email' => 'member-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$this->organisation->users()->attach($this->user->id);
	}

	// ─── Organisations ───

	public function testOrganisationIndexOnlyListsOwnOrganisations(): void
	{
		Passport::actingAs($this->user);

		$response = $this->getJson('/api/v1/organisations?fields=id,name,secret');
		$response->assertStatus(200);

		$ids = array_column($response->json('items'), 'id');
		$this->assertContains($this->organisation->id, $ids);
		$this->assertNotContains($this->otherOrganisation->id, $ids);
	}

	public function testOrganisationSecretIsNotExposedOnManagementApi(): void
	{
		$this->organisation->secret = 'super-secret';
		$this->organisation->save();

		Passport::actingAs($this->user);

		$response = $this->getJson('/api/v1/organisations/' . $this->organisation->id . '?fields=id,secret');
		$response->assertStatus(200);
		$this->assertStringNotContainsString('super-secret', $response->getContent());
	}

	public function testOrganisationSecretIsExposedToItsOwnDevices(): void
	{
		$this->organisation->secret = 'super-secret';
		$this->organisation->save();

		$token = $this->createDeviceToken($this->organisation);

		$response = $this
			->withHeader('Authorization', 'Bearer ' . $token)
			->getJson('/pos-api/v1/organisations/' . $this->organisation->id . '?fields=id,secret');

		$response->assertStatus(200);
		$this->assertEquals('super-secret', $response->json('secret'));
	}

	// ─── Events ───

	public function testEventIndexDeniedForOtherOrganisation(): void
	{
		Event::factory()->create(['organisation_id' => $this->otherOrganisation->id]);

		Passport::actingAs($this->user);

		$this->getJson('/api/v1/organisations/' . $this->otherOrganisation->id . '/events')
			->assertStatus(403);

		$this->getJson('/api/v1/organisations/' . $this->organisation->id . '/events')
			->assertStatus(200);
	}

	public function testEventCreateDeniedForOtherOrganisation(): void
	{
		Passport::actingAs($this->user);

		$this->postJson('/api/v1/organisations/' . $this->otherOrganisation->id . '/events', [
			'name' => 'Not mine',
		])->assertStatus(403);
	}

	// ─── Devices and payment gateways ───

	public function testDeviceIndexDeniedForOtherOrganisation(): void
	{
		Passport::actingAs($this->user);

		$this->getJson('/api/v1/organisations/' . $this->otherOrganisation->id . '/devices')
			->assertStatus(403);
	}

	public function testPaymentGatewayCreateDeniedForOtherOrganisation(): void
	{
		Passport::actingAs($this->user);

		$this->postJson('/api/v1/organisations/' . $this->otherOrganisation->id . '/payment-gateways', [
			'gateway' => 'paynl',
			'is_active' => true,
			'credentials' => ['apiToken' => 'a', 'apiSecret' => 'b', 'serviceId' => 'c'],
		])->assertStatus(403);
	}

	// ─── Attendees ───

	public function testAttendeeImportDeniedForOtherOrganisation(): void
	{
		$event = Event::factory()->create(['organisation_id' => $this->otherOrganisation->id]);
		$event->attendees()->create(['alias' => 'keep', 'name' => 'Keep Me']);

		Passport::actingAs($this->user);

		$this->putJson('/api/v1/events/' . $event->id . '/attendees/import', [
			'attendees' => "evil: Attacker",
		])->assertStatus(403);

		$this->assertEquals(1, Attendee::where('event_id', $event->id)->count());
		$this->assertEquals('keep', Attendee::where('event_id', $event->id)->first()->alias);
	}

	public function testAttendeeImportWorksForOwnOrganisation(): void
	{
		$event = Event::factory()->create(['organisation_id' => $this->organisation->id]);

		Passport::actingAs($this->user);

		$this->putJson('/api/v1/events/' . $event->id . '/attendees/import', [
			'attendees' => [ 'alpha: Alice' ],
		])->assertStatus(422);

		$this->putJson('/api/v1/events/' . $event->id . '/attendees/import', [
			'attendees' => "alpha: Alice\tAlice@example.com\nbeta: Bob",
		])->assertStatus(200);

		$this->assertEquals(2, Attendee::where('event_id', $event->id)->count());
	}

	// ─── Public orders ───

	public function testPublicOrderRejectsNegativeQuantities(): void
	{
		$event = Event::factory()->create([
			'organisation_id' => $this->organisation->id,
			'is_selling' => true,
			'order_token_secret' => null,
		]);
		$menuItem = MenuItem::factory()->create(['event_id' => $event->id, 'price' => 2.5]);

		$card = $this->createCardWithBalance($this->organisation, 1000);

		$response = $this->withHeaders([
			'X-Event-Token' => $event->order_token,
			'X-Card-Token' => $card->order_token,
		])->postJson('/api/v1/public/order.json', [
			'location' => 'Table 1',
			'order' => [
				'items' => [
					['menuItem' => ['id' => $menuItem->id], 'amount' => -40],
				],
			],
		]);

		$response->assertStatus(422);
		$this->assertEquals(1000, $card->fresh()->getBalance());
	}

	public function testPublicOrderDoesNotAcceptCardsFromOtherOrganisations(): void
	{
		$event = Event::factory()->create([
			'organisation_id' => $this->organisation->id,
			'is_selling' => true,
			'order_token_secret' => null,
			'allow_unpaid_online_orders' => false,
		]);
		$menuItem = MenuItem::factory()->create(['event_id' => $event->id, 'price' => 2.5]);

		$foreignCard = $this->createCardWithBalance($this->otherOrganisation, 1000);

		$response = $this->withHeaders([
			'X-Event-Token' => $event->order_token,
			'X-Card-Token' => $foreignCard->order_token,
		])->postJson('/api/v1/public/order.json', [
			'location' => 'Table 1',
			'order' => [
				'items' => [
					['menuItem' => ['id' => $menuItem->id], 'amount' => 1],
				],
			],
		]);

		$response->assertStatus(402);
		$this->assertEquals(1000, $foreignCard->fresh()->getBalance());
	}

	// ─── Device pairing ───

	public function testPairingLocksAfterRepeatedWrongCodes(): void
	{
		$connectRequest = new DeviceConnectRequest();
		$connectRequest->token = Str::random(32);
		$connectRequest->organisation()->associate($this->organisation);
		$connectRequest->createdBy()->associate($this->user);
		$connectRequest->device_uid = (string) Str::uuid();
		$connectRequest->pairing_code = '123456';
		$connectRequest->expires_at = now()->addMinutes(2);
		$connectRequest->save();

		Passport::actingAs($this->user);

		for ($i = 0; $i < DeviceConnectRequest::MAX_PAIRING_ATTEMPTS; $i++) {
			$this->postJson('/api/v1/device-connect-requests/' . $connectRequest->token . '/pair', [
				'pairing_code' => '000000',
				'device_name' => 'Tablet',
			])->assertStatus(422);
		}

		// Even the right code no longer works once the request is locked.
		$this->postJson('/api/v1/device-connect-requests/' . $connectRequest->token . '/pair', [
			'pairing_code' => '123456',
			'device_name' => 'Tablet',
		])->assertStatus(422);

		$this->assertFalse((bool) $connectRequest->fresh()->pairing_code_accepted);

		// The admin UI keeps pinging the request; that must not revive it.
		$this->postJson('/api/v1/device-connect-requests/' . $connectRequest->token . '/ping')
			->assertStatus(200);

		$this->postJson('/api/v1/device-connect-requests/' . $connectRequest->token . '/pair', [
			'pairing_code' => '123456',
			'device_name' => 'Tablet',
		])->assertStatus(422);

		$this->assertFalse((bool) $connectRequest->fresh()->pairing_code_accepted);
	}

	public function testPairingDeniedForUsersOutsideTheOrganisation(): void
	{
		$connectRequest = new DeviceConnectRequest();
		$connectRequest->token = Str::random(32);
		$connectRequest->organisation()->associate($this->otherOrganisation);
		$connectRequest->createdBy()->associate($this->user);
		$connectRequest->device_uid = (string) Str::uuid();
		$connectRequest->pairing_code = '123456';
		$connectRequest->expires_at = now()->addMinutes(2);
		$connectRequest->save();

		Passport::actingAs($this->user);

		$this->postJson('/api/v1/device-connect-requests/' . $connectRequest->token . '/pair', [
			'pairing_code' => '123456',
			'device_name' => 'Tablet',
		])->assertStatus(403);

		$this->postJson('/api/v1/device-connect-requests/' . $connectRequest->token . '/ping')
			->assertStatus(403);
	}

	// ─── Device tokens ───

	public function testDeviceTokensAreStoredHashedAndLegacyTokensStillWork(): void
	{
		$device = Device::factory()->create(['organisation_id' => $this->organisation->id]);

		$token = $device->createAccessToken($this->user);
		$this->assertNotNull($token->plainTextToken);
		$this->assertNotEquals($token->plainTextToken, $token->access_token);
		$this->assertEquals(DeviceAccessToken::hash($token->plainTextToken), $token->access_token);

		$this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
			->getJson('/pos-api/v1/devices/current')
			->assertStatus(200);

		// A token stored in plain text (issued before hashing) is accepted and upgraded.
		$legacy = new DeviceAccessToken([
			'device_id' => $device->id,
			'access_token' => 'legacy-plain-token',
			'expires_at' => now()->addHour(),
		]);
		$legacy->created_by = $this->user->id;
		$legacy->save();

		$this->withHeader('Authorization', 'Bearer legacy-plain-token')
			->getJson('/pos-api/v1/devices/current')
			->assertStatus(200);

		// The stored hash itself is never a valid token.
		$this->withHeader('Authorization', 'Bearer ' . $token->access_token)
			->getJson('/pos-api/v1/devices/current')
			->assertStatus(401);

		$this->assertEquals(DeviceAccessToken::hash('legacy-plain-token'), $legacy->fresh()->access_token);
	}

	// ─── Helpers ───

	private function createDeviceToken(Organisation $organisation): string
	{
		$device = Device::factory()->create(['organisation_id' => $organisation->id]);
		return $device->createAccessToken($this->user)->plainTextToken;
	}

	private function createCardWithBalance(Organisation $organisation, int $balance): Card
	{
		$card = Card::getFromUid($organisation, 'card-' . Str::random(8));

		$tx = new Transaction();
		$tx->card_id = $card->id;
		$tx->card_sync_id = 1;
		$tx->value = $balance;
		$tx->transaction_type = Transaction::TYPE_TOPUP;
		$tx->has_synced = true;
		$tx->save();

		return $card;
	}
}
