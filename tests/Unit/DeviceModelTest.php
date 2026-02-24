<?php

namespace Tests\Unit;

use App\Models\Device;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Device model attributes (casts, fillable, table).
 */
class DeviceModelTest extends TestCase
{
	/**
	 * Test that allow_remote_orders is cast to boolean.
	 */
	public function testAllowRemoteOrdersCast(): void
	{
		$device = new Device();
		$casts = $device->getCasts();

		$this->assertArrayHasKey('allow_remote_orders', $casts);
		$this->assertEquals('boolean', $casts['allow_remote_orders']);
	}

	/**
	 * Test that allow_live_orders is cast to boolean.
	 */
	public function testAllowLiveOrdersCast(): void
	{
		$device = new Device();
		$casts = $device->getCasts();

		$this->assertArrayHasKey('allow_live_orders', $casts);
		$this->assertEquals('boolean', $casts['allow_live_orders']);
	}

	/**
	 * Test that last_ping is cast to datetime.
	 */
	public function testLastPingCast(): void
	{
		$device = new Device();
		$casts = $device->getCasts();

		$this->assertArrayHasKey('last_ping', $casts);
		$this->assertEquals('datetime', $casts['last_ping']);
	}

	/**
	 * Test that fillable attributes include new fields.
	 */
	public function testFillableAttributes(): void
	{
		$device = new Device();
		$fillable = $device->getFillable();

		$this->assertContains('category_filter_id', $fillable);
		$this->assertContains('allow_remote_orders', $fillable);
		$this->assertContains('allow_live_orders', $fillable);
	}

	/**
	 * Test that the table name is correct.
	 */
	public function testTableName(): void
	{
		$device = new Device();
		$this->assertEquals('devices', $device->getTable());
	}
}
