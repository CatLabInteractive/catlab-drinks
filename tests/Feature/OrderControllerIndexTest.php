<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderControllerIndexTest extends TestCase
{
	use RefreshDatabase;

	public function testDeviceApiOrderIndexWorks(): void
	{
		$organisation = Organisation::factory()->create();

		$event = Event::factory()->create([
			'organisation_id' => $organisation->id,
		]);

		$device = Device::factory()->create([
			'organisation_id' => $organisation->id,
		]);

		$user = User::query()->create([
			'name' => 'Test User',
			'email' => 'test-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);

		$order = Order::factory()->make([
			'event_id' => $event->id,
		]);
		$order->saveQuietly();

		$token = new DeviceAccessToken([
			'device_id' => $device->id,
			'access_token' => 'test-access-token',
			'expires_at' => now()->addHour(),
		]);
		$token->created_by = $user->id;
		$token->save();

		$response = $this
			->withHeader('Authorization', 'Bearer ' . $token->access_token)
			->getJson('/pos-api/v1/events/' . $event->id . '/orders');

		$response->assertStatus(200);
		$response->assertJsonFragment([
			'id' => $order->id
		]);
	}
}
