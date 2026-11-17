<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Device;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An order that is split by station must only go to a device of the
 * station of its own products, not to any device of the original order.
 */
class SplitOrderAssignmentTest extends TestCase
{
	use RefreshDatabase;

	private Event $event;
	private MenuItem $beer;
	private MenuItem $fries;
	private Device $barDevice;
	private Device $kitchenDevice;

	protected function setUp(): void
	{
		parent::setUp();

		Carbon::setTestNow(Carbon::create(2026, 1, 15, 12, 0, 0));

		$organisation = Organisation::factory()->create();

		$this->event = Event::factory()->create([
			'organisation_id' => $organisation->id,
			'order_token' => 'splitordertoken12345678901',
			'order_token_secret' => null,
			'is_selling' => true,
			'allow_unpaid_online_orders' => true,
			'split_orders_by_categories' => true,
		]);

		$bar = Category::factory()->create(['name' => 'Bar', 'event_id' => $this->event->id]);
		$kitchen = Category::factory()->create(['name' => 'Kitchen', 'event_id' => $this->event->id]);

		$this->beer = MenuItem::factory()->create([
			'event_id' => $this->event->id,
			'category_id' => $bar->id,
			'price' => 2.5,
			'is_selling' => true,
		]);
		$this->fries = MenuItem::factory()->create([
			'event_id' => $this->event->id,
			'category_id' => $kitchen->id,
			'price' => 4,
			'is_selling' => true,
		]);

		$deviceDefaults = [
			'organisation_id' => $organisation->id,
			'last_ping' => Carbon::now(),
			'allow_remote_orders' => true,
			'allow_sales' => true,
		];
		$this->barDevice = Device::factory()->create(array_merge($deviceDefaults, [
			'name' => 'Bar POS',
			'category_filter_id' => $bar->id,
		]));
		$this->kitchenDevice = Device::factory()->create(array_merge($deviceDefaults, [
			'name' => 'Kitchen POS',
			'category_filter_id' => $kitchen->id,
		]));
	}

	protected function tearDown(): void
	{
		Carbon::setTestNow();
		parent::tearDown();
	}

	private function placeOrder(array $headers = []): void
	{
		$response = $this->withHeaders(array_merge([
			'X-Event-Token' => $this->event->order_token,
		], $headers))->postJson('/api/v1/public/order.json', [
			'location' => 'Table 1',
			'requester' => 'Test',
			'order' => [
				'items' => [
					['menuItem' => ['id' => $this->beer->id], 'amount' => 1],
					['menuItem' => ['id' => $this->fries->id], 'amount' => 1],
				],
			],
		]);

		$response->assertStatus(200);
	}

	public function testEachSplitOrderGoesToTheDeviceOfItsStation(): void
	{
		// The bar is busy, so a kitchen device that wrongly accepts bar
		// products would win the workload balance.
		for ($i = 0; $i < 3; $i++) {
			$busy = Order::factory()->make([
				'event_id' => $this->event->id,
				'status' => Order::STATUS_PENDING,
				'assigned_device_id' => $this->barDevice->id,
			]);
			$busy->saveQuietly();
		}

		for ($i = 0; $i < 2; $i++) {
			$this->placeOrder();
		}

		$orders = Order::where('event_id', $this->event->id)
			->whereHas('order')
			->with('order.menuItem')
			->get();
		$this->assertCount(4, $orders);

		foreach ($orders as $order) {
			$this->assertCount(1, $order->order);
			$expected = $order->order[0]->menu_item_id === $this->beer->id
				? $this->barDevice->id
				: $this->kitchenDevice->id;

			$this->assertEquals($expected, $order->assigned_device_id);
		}
	}

	public function testCardIsChargedTheOrderTotalOnce(): void
	{
		$card = new Card();
		$card->organisation_id = $this->event->organisation_id;
		$card->uid = 'splitordercard01';
		$card->order_token = 'splitordercardtoken0123456789';
		$card->discount_percentage = 0;
		$card->transaction_count = 0;
		$card->save();

		$topup = new Transaction();
		$topup->card()->associate($card);
		$topup->transaction_type = Transaction::TYPE_TOPUP;
		$topup->has_synced = false;
		$topup->value = 5000;
		$topup->save();

		$this->placeOrder(['X-Card-Token' => $card->order_token]);

		// Beer 2.50 + fries 4.00
		$this->assertEquals(5000 - 650, $card->fresh()->getBalance());

		$charges = Transaction::where('card_id', $card->id)
			->where('transaction_type', Transaction::TYPE_SALE)
			->pluck('value')
			->sort()
			->values()
			->all();
		$this->assertEquals([-400, -250], $charges);
	}
}
