<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Event;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OrderAssignmentService
{
	/**
	 * Assign a single order to an appropriate online device.
	 * @param Order $order
	 * @return void
	 */
	public function assignOrder(Order $order): void
	{
		$event = $order->event;
		if (!$event) {
			return;
		}

		$orderCategoryIds = $this->getOrderCategoryIds($order);
		$device = $this->findBestDevice($event, $orderCategoryIds);

		if ($device) {
			$order->assigned_device_id = $device->id;
			$order->saveQuietly();
		}
	}

	/**
	 * Reassign pending orders from offline devices to online devices,
	 * and attempt to assign any stranded (unassigned) orders.
	 * Should be called during order listing to keep assignments current.
	 * @param Event $event
	 * @return void
	 */
	public function reassignOfflineDeviceOrders(Event $event): void
	{
		$gracePeriod = config('devices.reassignment_grace_period', 300);
		$cutoff = Carbon::now()->subSeconds($gracePeriod);

		// Find pending orders that are either:
		// 1. Assigned to devices that have gone offline (past reassignment grace period)
		// 2. Unassigned (stranded) — try to find a compatible online device
		$ordersToReassign = Order::where('event_id', $event->id)
			->where('status', Order::STATUS_PENDING)
			->where(function ($query) use ($cutoff) {
				$query->whereNull('assigned_device_id')
					->orWhereHas('assignedDevice', function ($q) use ($cutoff) {
						$q->where(function ($inner) use ($cutoff) {
							$inner->whereNull('last_ping')
								->orWhere('last_ping', '<', $cutoff);
						});
					});
			})
			->get();

		foreach ($ordersToReassign as $order) {
			$orderCategoryIds = $this->getOrderCategoryIds($order);
			$device = $this->findBestDevice($event, $orderCategoryIds);

			$order->assigned_device_id = $device ? $device->id : null;
			$order->saveQuietly();
		}
	}

	/**
	 * Re-evaluate all pending order assignments for an event.
	 * Called when a device changes its category filter.
	 * Orders assigned to the device that changed filter and no longer matching
	 * are reassigned, even if the device is online.
	 * @param Event $event
	 * @param Device|null $changedDevice The device that changed its filter
	 * @return void
	 */
	public function reevaluateAssignments(Event $event, ?Device $changedDevice = null): void
	{
		$pendingOrders = Order::where('event_id', $event->id)
			->where('status', Order::STATUS_PENDING)
			->get();

		foreach ($pendingOrders as $order) {
			if ($order->assigned_device_id) {
				$device = Device::find($order->assigned_device_id);
				if ($device && $device->isOnline()) {
					// If this order belongs to the device that just changed its filter,
					// check if the order still matches the new filter
					if ($changedDevice && $device->id === $changedDevice->id) {
						$orderCategoryIds = $this->getOrderCategoryIds($order);
						if ($this->deviceMatchesOrder($device, $orderCategoryIds)) {
							// Still matches, keep assignment
							continue;
						}
						// No longer matches — fall through to reassign
					} else {
						// Don't reassign from other online devices - crew might be working on it
						continue;
					}
				}
			}

			$orderCategoryIds = $this->getOrderCategoryIds($order);
			$device = $this->findBestDevice($event, $orderCategoryIds);

			$order->assigned_device_id = $device ? $device->id : null;
			$order->saveQuietly();
		}
	}

	/**
	 * Count pending orders that are stranded (unassigned because no online
	 * device accepts their category).
	 * @param Event $event
	 * @return int
	 */
	public function countStrandedOrders(Event $event): int
	{
		return Order::where('event_id', $event->id)
			->where('status', Order::STATUS_PENDING)
			->whereNull('assigned_device_id')
			->count();
	}

	/**
	 * Check if a device's category filter matches an order's categories.
	 * @param Device $device
	 * @param array $orderCategoryIds
	 * @return bool
	 */
	private function deviceMatchesOrder(Device $device, array $orderCategoryIds): bool
	{
		// Device with no category filter accepts all orders
		if (!$device->category_filter_id) {
			return true;
		}

		// If order has no category items, any device can handle it
		if (empty($orderCategoryIds)) {
			return true;
		}

		return in_array($device->category_filter_id, $orderCategoryIds);
	}

	/**
	 * Get category IDs for all items in an order.
	 * @param Order $order
	 * @return array
	 */
	private function getOrderCategoryIds(Order $order): array
	{
		$categoryIds = [];
		foreach ($order->order as $orderItem) {
			if ($orderItem->menuItem && $orderItem->menuItem->category) {
				$categoryIds[] = $orderItem->menuItem->category->id;
			}
		}
		return array_unique($categoryIds);
	}

	/**
	 * Find the best online device to handle an order based on workload and category compatibility.
	 * Returns null if no compatible online device is found (order stays unassigned / stranded).
	 * @param Event $event
	 * @param array $orderCategoryIds
	 * @return Device|null
	 */
	private function findBestDevice(Event $event, array $orderCategoryIds): ?Device
	{
		$gracePeriod = config('devices.reassignment_grace_period', 300);
		$cutoff = Carbon::now()->subSeconds($gracePeriod);

		// Get all online devices for this organisation
		$onlineDevices = Device::where('organisation_id', $event->organisation_id)
			->where('last_ping', '>', $cutoff)
			->get();

		if ($onlineDevices->isEmpty()) {
			return null;
		}

		// Filter online devices by category compatibility
		$compatibleDevices = $onlineDevices->filter(function (Device $device) use ($orderCategoryIds) {
			return $this->deviceMatchesOrder($device, $orderCategoryIds);
		});

		if ($compatibleDevices->isEmpty()) {
			// No category-compatible online device found — order will be stranded (NULL)
			return null;
		}

		return $this->deviceWithLowestWorkload($compatibleDevices);
	}

	/**
	 * Pick the device with the fewest pending orders from a collection.
	 * @param Collection $devices
	 * @return Device|null
	 */
	private function deviceWithLowestWorkload(Collection $devices): ?Device
	{
		$bestDevice = null;
		$lowestWorkload = PHP_INT_MAX;

		foreach ($devices as $device) {
			$workload = Order::where('assigned_device_id', $device->id)
				->where('status', Order::STATUS_PENDING)
				->count();

			if ($workload < $lowestWorkload) {
				$lowestWorkload = $workload;
				$bestDevice = $device;
			}
		}

		return $bestDevice;
	}
}
