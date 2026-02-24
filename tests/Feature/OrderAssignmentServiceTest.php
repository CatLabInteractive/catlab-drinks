<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Services\OrderAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integration tests for OrderAssignmentService and Device model.
 *
 * Uses MySQL database with RefreshDatabase to test the full assignment logic
 * including workload balancing, category filtering, offline reassignment,
 * stranded order detection, and device online status.
 */
class OrderAssignmentServiceTest extends TestCase
{
	use RefreshDatabase;

	private OrderAssignmentService $service;
	private Organisation $organisation;
	private Event $event;

	protected function setUp(): void
	{
		parent::setUp();

		Carbon::setTestNow(Carbon::create(2026, 1, 15, 12, 0, 0));

		$this->service = new OrderAssignmentService();

		$this->organisation = Organisation::factory()->create();

		$this->event = Event::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);
	}

	protected function tearDown(): void
	{
		Carbon::setTestNow();
		parent::tearDown();
	}

	/**
	 * Create a test device with organisation defaulting to $this->organisation.
	 */
	private function createDevice(array $attrs = []): Device
	{
		return Device::factory()->create(array_merge([
			'organisation_id' => $this->organisation->id,
		], $attrs));
	}

	/**
	 * Create a test order (without triggering auto-assignment).
	 */
	private function createOrder(array $attrs = []): Order
	{
		$order = Order::factory()->make(array_merge([
			'event_id' => $this->event->id,
		], $attrs));
		$order->saveQuietly();
		return $order;
	}

	// ──────────────────────────────────────────────────
	// Device Online Status Tests
	// ──────────────────────────────────────────────────

	/**
	 * Test that a device with no last_ping is offline.
	 */
	public function testDeviceIsOfflineWithNoLastPing(): void
	{
		$device = $this->createDevice(['last_ping' => null]);
		$this->assertFalse($device->isOnline());
	}

	/**
	 * Test that a device with a recent ping is online.
	 */
	public function testDeviceIsOnlineWithRecentPing(): void
	{
		$device = $this->createDevice(['last_ping' => Carbon::now()->subSeconds(30)]);
		$this->assertTrue($device->isOnline());
	}

	/**
	 * Test that a device with a stale ping is offline.
	 */
	public function testDeviceIsOfflineWithOldPing(): void
	{
		$device = $this->createDevice(['last_ping' => Carbon::now()->subSeconds(120)]);
		$this->assertFalse($device->isOnline());
	}

	/**
	 * Test that a device at the exact boundary is offline.
	 */
	public function testDeviceIsOfflineAtExactBoundary(): void
	{
		$device = $this->createDevice(['last_ping' => Carbon::now()->subSeconds(60)]);
		$this->assertFalse($device->isOnline());
	}

	/**
	 * Test that a device just inside the grace period is online.
	 */
	public function testDeviceIsOnlineJustInsideGracePeriod(): void
	{
		$device = $this->createDevice(['last_ping' => Carbon::now()->subSeconds(59)]);
		$this->assertTrue($device->isOnline());
	}

	/**
	 * Test touchLastPing sets the ping to now.
	 */
	public function testTouchLastPing(): void
	{
		$device = $this->createDevice(['last_ping' => null]);
		$this->assertFalse($device->isOnline());

		$device->touchLastPing();
		$device->refresh();

		$this->assertTrue($device->isOnline());
		$this->assertEquals(Carbon::now()->format('Y-m-d H:i:s'), $device->last_ping->format('Y-m-d H:i:s'));
	}

	/**
	 * Test pendingOrdersCount accessor.
	 */
	public function testPendingOrdersCount(): void
	{
		$device = $this->createDevice();

		$this->assertEquals(0, $device->pendingOrdersCount);

		// Add pending orders
		$this->createOrder(['assigned_device_id' => $device->id]);
		$this->createOrder(['assigned_device_id' => $device->id]);
		$this->createOrder(['assigned_device_id' => $device->id, 'status' => Order::STATUS_PROCESSED]);

		$this->assertEquals(2, $device->pendingOrdersCount);
	}

	// ──────────────────────────────────────────────────
	// Assignment Tests
	// ──────────────────────────────────────────────────

	/**
	 * Test that assignOrder picks an online device.
	 */
	public function testAssignOrderToOnlineDevice(): void
	{
		$device = $this->createDevice(['name' => 'POS 1']);
		$order = $this->createOrder();

		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertEquals($device->id, $order->assigned_device_id);
	}

	/**
	 * Test that assignOrder returns null when no online device exists.
	 */
	public function testAssignOrderReturnsNullWhenNoOnlineDevice(): void
	{
		// Device is offline (no ping)
		$device = $this->createDevice(['name' => 'Offline POS', 'last_ping' => null]);

		// Verify device has null last_ping
		$device->refresh();
		$this->assertNull($device->last_ping);

		$order = $this->createOrder();
		$this->assertNull($order->assigned_device_id, 'Order should start unassigned');

		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertNull($order->assigned_device_id, 'Order should remain unassigned when only offline devices exist');
	}

	/**
	 * Test that assignOrder picks the device with the fewest pending orders (load balancing).
	 */
	public function testAssignOrderBalancesWorkload(): void
	{
		$device1 = $this->createDevice(['name' => 'POS 1']);
		$device2 = $this->createDevice(['name' => 'POS 2']);

		// Give device1 3 pending orders
		for ($i = 0; $i < 3; $i++) {
			$this->createOrder(['assigned_device_id' => $device1->id]);
		}
		// Give device2 1 pending order
		$this->createOrder(['assigned_device_id' => $device2->id]);

		$newOrder = $this->createOrder();
		$this->service->assignOrder($newOrder);

		$newOrder->refresh();
		// Should pick device2 since it has fewer pending orders
		$this->assertEquals($device2->id, $newOrder->assigned_device_id);
	}

	/**
	 * Test that assignOrder excludes devices with allow_remote_orders=false.
	 */
	public function testAssignOrderExcludesNonRemoteDevices(): void
	{
		$this->createDevice(['name' => 'Non-remote POS', 'allow_remote_orders' => false]);
		$remoteDevice = $this->createDevice(['name' => 'Remote POS', 'allow_remote_orders' => true]);

		$order = $this->createOrder();
		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertEquals($remoteDevice->id, $order->assigned_device_id);
	}

	/**
	 * Test that assignOrder excludes offline devices (last_ping too old).
	 */
	public function testAssignOrderExcludesOfflineDevices(): void
	{
		// Offline device (pinged 400 seconds ago, > 300s reassignment grace period)
		$this->createDevice([
			'name' => 'Old POS',
			'last_ping' => Carbon::now()->subSeconds(400),
		]);

		$onlineDevice = $this->createDevice([
			'name' => 'Online POS',
			'last_ping' => Carbon::now()->subSeconds(10),
		]);

		$order = $this->createOrder();
		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertEquals($onlineDevice->id, $order->assigned_device_id);
	}

	// ──────────────────────────────────────────────────
	// Category Filtering Tests
	// ──────────────────────────────────────────────────

	/**
	 * Test that assignOrder respects device category filter.
	 */
	public function testAssignOrderRespectsCategory(): void
	{
		$foodCategory = Category::factory()->create(['name' => 'Food', 'event_id' => $this->event->id]);
		$drinkCategory = Category::factory()->create(['name' => 'Drinks', 'event_id' => $this->event->id]);

		// Device only accepts food
		$foodDevice = $this->createDevice([
			'name' => 'Food POS',
			'category_filter_id' => $foodCategory->id,
		]);

		// Device only accepts drinks
		$drinkDevice = $this->createDevice([
			'name' => 'Drink POS',
			'category_filter_id' => $drinkCategory->id,
		]);

		$foodItem = MenuItem::factory()->create(['event_id' => $this->event->id, 'category_id' => $foodCategory->id]);

		// Create order with a food item
		$order = $this->createOrder();
		OrderItem::factory()->create(['order_id' => $order->id, 'menu_item_id' => $foodItem->id, 'amount' => 1, 'price' => $foodItem->price]);

		$this->service->assignOrder($order);

		$order->refresh();
		// Should be assigned to the food device
		$this->assertEquals($foodDevice->id, $order->assigned_device_id);
	}

	/**
	 * Test that a device with no category filter accepts all orders.
	 */
	public function testDeviceWithNoFilterAcceptsAll(): void
	{
		$foodCategory = Category::factory()->create(['name' => 'Food', 'event_id' => $this->event->id]);
		$foodItem = MenuItem::factory()->create(['event_id' => $this->event->id, 'category_id' => $foodCategory->id]);

		// Device with no category filter
		$device = $this->createDevice(['name' => 'General POS']);

		// Create order with a food item
		$order = $this->createOrder();
		OrderItem::factory()->create(['order_id' => $order->id, 'menu_item_id' => $foodItem->id, 'amount' => 1, 'price' => $foodItem->price]);

		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertEquals($device->id, $order->assigned_device_id);
	}

	/**
	 * Test that orders with no category items can go to any device.
	 */
	public function testOrderWithNoCategoryGoesToAnyDevice(): void
	{
		$category = Category::factory()->create(['name' => 'Food', 'event_id' => $this->event->id]);
		$device = $this->createDevice([
			'name' => 'Food POS',
			'category_filter_id' => $category->id,
		]);

		// Order with no items (no category)
		$order = $this->createOrder();

		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertEquals($device->id, $order->assigned_device_id);
	}

	/**
	 * Test that order is stranded when no device matches its category.
	 */
	public function testOrderStrandedWhenNoCategoryMatch(): void
	{
		$foodCategory = Category::factory()->create(['name' => 'Food', 'event_id' => $this->event->id]);
		$drinkCategory = Category::factory()->create(['name' => 'Drinks', 'event_id' => $this->event->id]);
		$drinkItem = MenuItem::factory()->create(['event_id' => $this->event->id, 'category_id' => $drinkCategory->id]);

		// Only a food device is online
		$this->createDevice([
			'name' => 'Food POS',
			'category_filter_id' => $foodCategory->id,
		]);

		// Create a drinks order
		$order = $this->createOrder();
		OrderItem::factory()->create(['order_id' => $order->id, 'menu_item_id' => $drinkItem->id, 'amount' => 1, 'price' => $drinkItem->price]);

		$this->service->assignOrder($order);

		$order->refresh();
		// No matching device — order should be stranded (NULL)
		$this->assertNull($order->assigned_device_id);
	}

	// ──────────────────────────────────────────────────
	// Offline Reassignment Tests
	// ──────────────────────────────────────────────────

	/**
	 * Test that orders from offline devices are reassigned.
	 */
	public function testReassignOfflineDeviceOrders(): void
	{
		$offlineDevice = $this->createDevice([
			'name' => 'Offline POS',
			'last_ping' => Carbon::now()->subSeconds(400), // past 300s grace period
		]);

		$onlineDevice = $this->createDevice([
			'name' => 'Online POS',
			'last_ping' => Carbon::now()->subSeconds(10),
		]);

		// Order assigned to offline device
		$order = $this->createOrder(['assigned_device_id' => $offlineDevice->id]);

		$this->service->reassignOfflineDeviceOrders($this->event);

		$order->refresh();
		$this->assertEquals($onlineDevice->id, $order->assigned_device_id);
	}

	/**
	 * Test that stranded orders are retried during reassignment.
	 */
	public function testReassignRetriesStrandedOrders(): void
	{
		// Initially no online device, order is stranded
		$order = $this->createOrder(['assigned_device_id' => null]);

		// Now an online device appears
		$device = $this->createDevice(['name' => 'New POS']);

		$this->service->reassignOfflineDeviceOrders($this->event);

		$order->refresh();
		$this->assertEquals($device->id, $order->assigned_device_id);
	}

	/**
	 * Test that orders on devices within the grace period are NOT reassigned.
	 */
	public function testOrdersWithinGracePeriodNotReassigned(): void
	{
		$recentDevice = $this->createDevice([
			'name' => 'Recent POS',
			'last_ping' => Carbon::now()->subSeconds(200), // within 300s grace period
		]);

		$onlineDevice = $this->createDevice([
			'name' => 'Online POS',
			'last_ping' => Carbon::now()->subSeconds(10),
		]);

		$order = $this->createOrder(['assigned_device_id' => $recentDevice->id]);

		$this->service->reassignOfflineDeviceOrders($this->event);

		$order->refresh();
		// Should NOT be reassigned — device is still within grace period
		$this->assertEquals($recentDevice->id, $order->assigned_device_id);
	}

	/**
	 * Test that processed orders are not reassigned.
	 */
	public function testProcessedOrdersNotReassigned(): void
	{
		$offlineDevice = $this->createDevice([
			'name' => 'Offline POS',
			'last_ping' => Carbon::now()->subSeconds(400),
		]);

		$onlineDevice = $this->createDevice(['name' => 'Online POS']);

		$order = $this->createOrder([
			'assigned_device_id' => $offlineDevice->id,
			'status' => Order::STATUS_PROCESSED,
		]);

		$this->service->reassignOfflineDeviceOrders($this->event);

		$order->refresh();
		// Should NOT be reassigned — it's already processed
		$this->assertEquals($offlineDevice->id, $order->assigned_device_id);
	}

	// ──────────────────────────────────────────────────
	// Reevaluation Tests
	// ──────────────────────────────────────────────────

	/**
	 * Test that reevaluateAssignments reassigns orders when device changes category.
	 */
	public function testReevaluateReassignsOnCategoryChange(): void
	{
		$foodCategory = Category::factory()->create(['name' => 'Food', 'event_id' => $this->event->id]);
		$drinkCategory = Category::factory()->create(['name' => 'Drinks', 'event_id' => $this->event->id]);
		$drinkItem = MenuItem::factory()->create(['event_id' => $this->event->id, 'category_id' => $drinkCategory->id]);

		$device1 = $this->createDevice([
			'name' => 'POS 1',
			'category_filter_id' => null, // was accepting all, now only food
		]);
		$device2 = $this->createDevice([
			'name' => 'POS 2',
			'category_filter_id' => null, // accepts all
		]);

		// Order has drink items, assigned to device1
		$order = $this->createOrder(['assigned_device_id' => $device1->id]);
		OrderItem::factory()->create(['order_id' => $order->id, 'menu_item_id' => $drinkItem->id, 'amount' => 1, 'price' => $drinkItem->price]);

		// Device1 now only accepts food
		$device1->category_filter_id = $foodCategory->id;
		$device1->saveQuietly(); // avoid triggering model events

		$this->service->reevaluateAssignments($this->event, $device1);

		$order->refresh();
		// Should be reassigned to device2 (accepts all categories)
		$this->assertEquals($device2->id, $order->assigned_device_id);
	}

	/**
	 * Test that reevaluateAssignments does NOT reassign orders from OTHER online devices.
	 */
	public function testReevaluateDoesNotReassignOtherOnlineDevices(): void
	{
		$device1 = $this->createDevice(['name' => 'POS 1']);
		$device2 = $this->createDevice(['name' => 'POS 2']);

		// Order assigned to device1
		$order = $this->createOrder(['assigned_device_id' => $device1->id]);

		// Device2 changed its category (not device1)
		$this->service->reevaluateAssignments($this->event, $device2);

		$order->refresh();
		// Should still be on device1 — it's online and wasn't the changed device
		$this->assertEquals($device1->id, $order->assigned_device_id);
	}

	/**
	 * Test that reevaluateAssignments reassigns when device disables remote orders.
	 */
	public function testReevaluateReassignsWhenRemoteDisabled(): void
	{
		$device1 = $this->createDevice([
			'name' => 'POS 1',
			'allow_remote_orders' => false, // just disabled
		]);
		$device2 = $this->createDevice(['name' => 'POS 2']);

		$order = $this->createOrder(['assigned_device_id' => $device1->id]);

		$this->service->reevaluateAssignments($this->event, $device1);

		$order->refresh();
		// Should be reassigned — device1 no longer accepts remote orders
		$this->assertEquals($device2->id, $order->assigned_device_id);
	}

	// ──────────────────────────────────────────────────
	// Stranded Orders Tests
	// ──────────────────────────────────────────────────

	/**
	 * Test countStrandedOrders returns correct count.
	 */
	public function testCountStrandedOrders(): void
	{
		// Create 3 stranded orders (no assigned device)
		$this->createOrder(['assigned_device_id' => null]);
		$this->createOrder(['assigned_device_id' => null]);
		$this->createOrder(['assigned_device_id' => null]);

		$device = $this->createDevice(['name' => 'POS 1']);
		// Create 2 assigned orders
		$this->createOrder(['assigned_device_id' => $device->id]);
		$this->createOrder(['assigned_device_id' => $device->id]);

		$count = $this->service->countStrandedOrders($this->event);
		$this->assertEquals(3, $count);
	}

	/**
	 * Test countStrandedOrders returns 0 when all orders are assigned.
	 */
	public function testCountStrandedOrdersZeroWhenAllAssigned(): void
	{
		$device = $this->createDevice(['name' => 'POS 1']);
		$this->createOrder(['assigned_device_id' => $device->id]);

		$count = $this->service->countStrandedOrders($this->event);
		$this->assertEquals(0, $count);
	}

	/**
	 * Test countStrandedOrders excludes non-pending orders.
	 */
	public function testCountStrandedExcludesProcessedOrders(): void
	{
		// Processed unassigned order should not be counted
		$this->createOrder([
			'assigned_device_id' => null,
			'status' => Order::STATUS_PROCESSED,
		]);

		$count = $this->service->countStrandedOrders($this->event);
		$this->assertEquals(0, $count);
	}

	// ──────────────────────────────────────────────────
	// Edge Cases
	// ──────────────────────────────────────────────────

	/**
	 * Test that assignOrder does nothing when the order has no event.
	 */
	public function testAssignOrderWithNoEvent(): void
	{
		$order = Order::factory()->make([
			'event_id' => null,
			'location' => 'Table 1',
		]);
		$order->saveQuietly();

		// Should not throw or crash
		$this->service->assignOrder($order);

		$order->refresh();
		$this->assertNull($order->assigned_device_id);
	}

	/**
	 * Test even distribution across multiple devices.
	 */
	public function testEvenDistributionAcrossDevices(): void
	{
		$device1 = $this->createDevice(['name' => 'POS 1']);
		$device2 = $this->createDevice(['name' => 'POS 2']);
		$device3 = $this->createDevice(['name' => 'POS 3']);

		$assignments = [];
		for ($i = 0; $i < 6; $i++) {
			$order = $this->createOrder();
			$this->service->assignOrder($order);
			$order->refresh();
			$assignments[] = $order->assigned_device_id;
		}

		// Each device should get 2 orders
		$counts = array_count_values($assignments);
		$this->assertEquals(2, $counts[$device1->id]);
		$this->assertEquals(2, $counts[$device2->id]);
		$this->assertEquals(2, $counts[$device3->id]);
	}
}
