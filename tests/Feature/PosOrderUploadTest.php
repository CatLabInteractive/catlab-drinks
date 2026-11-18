<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The POS uploads its sales in batches to the device API. Each order item links
 * to a product by its id. These tests hold the body that the POS must send.
 */
class PosOrderUploadTest extends TestCase
{
	use RefreshDatabase;

	private Event $event;

	private MenuItem $menuItem;

	private string $token;

	protected function setUp(): void
	{
		parent::setUp();

		$organisation = Organisation::factory()->create();

		$user = User::query()->create([
			'name' => 'Member',
			'email' => 'member-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$organisation->users()->attach($user->id);

		$this->event = Event::factory()->create(['organisation_id' => $organisation->id, 'is_selling' => true]);
		$this->menuItem = MenuItem::factory()->create(['event_id' => $this->event->id, 'price' => 2.5]);

		$device = Device::factory()->create(['organisation_id' => $organisation->id]);
		$this->token = $device->createAccessToken($user)->plainTextToken;
	}

	public function testABatchWithOnlyTheProductIdIsStored(): void
	{
		$response = $this->upload(['id' => $this->menuItem->id]);

		$response->assertStatus(200);

		$order = Order::query()->where('event_id', $this->event->id)->sole();
		$this->assertCount(1, $order->order);
		$this->assertEquals($this->menuItem->id, $order->order->first()->menu_item_id);
		$this->assertEquals(2, $order->order->first()->amount);
	}

	public function testAProductLinkWithOtherAttributesIsRejected(): void
	{
		$response = $this->upload(['id' => $this->menuItem->id, 'name' => $this->menuItem->name]);

		$response->assertStatus(422);
		$this->assertEquals(0, Order::query()->where('event_id', $this->event->id)->count());
	}

	private function upload(array $menuItem)
	{
		return $this
			->withHeaders(['Authorization' => 'Bearer ' . $this->token])
			->postJson('/pos-api/v1/events/' . $this->event->id . '/orders', [
				'items' => [
					[
						'uid' => (string) Str::uuid(),
						'location' => 'Manual',
						'status' => 'processed',
						'paid' => true,
						'price' => 5,
						'discount' => 0,
						'order' => [
							'items' => [
								['menuItem' => $menuItem, 'amount' => 2, 'price' => 2.5],
							],
						],
					],
				],
			]);
	}
}
