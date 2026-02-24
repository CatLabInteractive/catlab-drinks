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
	 * Reassign pending orders from offline devices to online devices.
	 * Should be called during order listing to keep assignments current.
	 * @param Event $event
	 * @return void
	 */
	public function reassignOfflineDeviceOrders(Event $event): void
	{
		$gracePeriod = config('devices.offline_grace_period', 300);
		$cutoff = Carbon::now()->subSeconds($gracePeriod);

		// Find pending orders assigned to devices that have gone offline
		$offlineOrders = Order::where('event_id', $event->id)
			->where('status', Order::STATUS_PENDING)
			->whereNotNull('assigned_device_id')
			->whereHas('assignedDevice', function ($query) use ($cutoff) {
				$query->where(function ($q) use ($cutoff) {
					$q->whereNull('last_ping')
						->orWhere('last_ping', '<', $cutoff);
				});
			})
			->get();

		foreach ($offlineOrders as $order) {
			$orderCategoryIds = $this->getOrderCategoryIds($order);
			$device = $this->findBestDevice($event, $orderCategoryIds);

			$order->assigned_device_id = $device ? $device->id : null;
			$order->saveQuietly();
		}
	}

	/**
	 * Re-evaluate all pending order assignments for an event.
	 * Called when a device changes its category filter.
	 * @param Event $event
	 * @return void
	 */
	public function reevaluateAssignments(Event $event): void
	{
		$pendingOrders = Order::where('event_id', $event->id)
			->where('status', Order::STATUS_PENDING)
			->get();

		foreach ($pendingOrders as $order) {
			// Only reassign orders from offline devices or unassigned orders
			if ($order->assigned_device_id) {
				$device = Device::find($order->assigned_device_id);
				if ($device && $device->isOnline()) {
					// Don't reassign from online devices - crew might be working on it
					continue;
				}
			}

			$orderCategoryIds = $this->getOrderCategoryIds($order);
			$device = $this->findBestDevice($event, $orderCategoryIds);

			$order->assigned_device_id = $device ? $device->id : null;
			$order->saveQuietly();
		}
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
	 * Find the best device to handle an order based on workload and category compatibility.
	 * @param Event $event
	 * @param array $orderCategoryIds
	 * @return Device|null
	 */
	private function findBestDevice(Event $event, array $orderCategoryIds): ?Device
	{
		$gracePeriod = config('devices.offline_grace_period', 300);
		$cutoff = Carbon::now()->subSeconds($gracePeriod);

		// Get all online devices for this organisation
		$onlineDevices = Device::where('organisation_id', $event->organisation_id)
			->where('last_ping', '>', $cutoff)
			->get();

		if ($onlineDevices->isEmpty()) {
			return null;
		}

		// Filter devices by category compatibility
		$compatibleDevices = $onlineDevices->filter(function (Device $device) use ($orderCategoryIds) {
			// Device with no category filter accepts all orders
			if (!$device->category_filter_id) {
				return true;
			}

			// If order has no category items, any device can handle it
			if (empty($orderCategoryIds)) {
				return true;
			}

			// Device must accept at least one of the order's categories
			return in_array($device->category_filter_id, $orderCategoryIds);
		});

		if ($compatibleDevices->isEmpty()) {
			// Fall back to any online device if no category-compatible device found
			$compatibleDevices = $onlineDevices;
		}

		// Find the device with the least pending orders (load balancing)
		$bestDevice = null;
		$lowestWorkload = PHP_INT_MAX;

		foreach ($compatibleDevices as $device) {
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
