<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\MenuHeading;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The sales summary is refreshed every 30 seconds, so the number of queries
 * must not grow with the number of products on the menu.
 */
class OrderSummaryTest extends TestCase
{
	use RefreshDatabase;

	public function testSummaryLoadsMenuItemsInFewQueries(): void
	{
		$organisation = Organisation::factory()->create();
		$user = User::query()->create([
			'name' => 'Member',
			'email' => 'member-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$organisation->users()->attach($user->id);

		$event = Event::factory()->create(['organisation_id' => $organisation->id]);

		$order = Order::factory()->create([
			'event_id' => $event->id,
			'status' => Order::STATUS_PROCESSED,
		]);

		$prices = [2.5, 3, 4, 4.5, 9];
		foreach ($prices as $index => $price) {
			$menuItem = MenuItem::factory()->create([
				'event_id' => $event->id,
				'price' => $price,
				'category_id' => Category::factory()->create(['event_id' => $event->id, 'name' => 'Station ' . $index])->id,
				'heading_id' => MenuHeading::factory()->create(['event_id' => $event->id, 'name' => 'Heading ' . $index])->id,
			]);

			OrderItem::factory()->create([
				'order_id' => $order->id,
				'menu_item_id' => $menuItem->id,
				'amount' => 1,
				'price' => $price,
			]);
		}

		Passport::actingAs($user);
		DB::flushQueryLog();
		DB::enableQueryLog();

		$response = $this->getJson('/api/v1/events/' . $event->id . '/ordersummary')->assertStatus(200);

		$queries = array_column(DB::getQueryLog(), 'query');
		DB::disableQueryLog();

		$this->assertCount(5, $response->json('items.items'));
		$this->assertEquals(array_sum($prices), $response->json('totalSales'));

		foreach (['menu_items', 'categories', 'menu_headings'] as $table) {
			$selects = array_filter($queries, fn ($query) => preg_match('/^select .* from [`"]?' . $table . '[`"]?/i', $query));
			$this->assertCount(1, $selects, 'Queries on ' . $table . ":\n" . implode("\n", $selects));
		}
	}
}
