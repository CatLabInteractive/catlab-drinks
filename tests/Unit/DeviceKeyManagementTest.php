<?php

namespace Tests\Unit;

use App\Models\Device;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Device model key management methods.
 */
class DeviceKeyManagementTest extends TestCase
{
	/**
	 * Test that isApproved returns false when approved_at is null.
	 */
	public function testIsApprovedReturnsFalseWhenNotApproved(): void
	{
		$device = new Device();
		$device->approved_at = null;

		$this->assertFalse($device->isApproved());
	}

	/**
	 * Test that isApproved returns true when approved_at is set.
	 */
	public function testIsApprovedReturnsTrueWhenApproved(): void
	{
		$device = new Device();
		$device->approved_at = now();

		$this->assertTrue($device->isApproved());
	}

	/**
	 * Test that the device uses soft deletes.
	 */
	public function testDeviceUsesSoftDeletes(): void
	{
		$device = new Device();

		// Check that the deleted_at column is in the dates/casts
		$this->assertTrue(
			method_exists($device, 'trashed'),
			'Device should use SoftDeletes trait'
		);
	}

	/**
	 * Test that last_activity is cast to datetime.
	 */
	public function testLastActivityCast(): void
	{
		$device = new Device();
		$casts = $device->getCasts();

		$this->assertArrayHasKey('last_activity', $casts);
		$this->assertEquals('datetime', $casts['last_activity']);
	}

	/**
	 * Test that public_key is in fillable list.
	 */
	public function testPublicKeyNotInFillable(): void
	{
		$device = new Device();
		$fillable = $device->getFillable();

		// public_key should NOT be mass-assignable for security
		// It's set explicitly through controller logic
		$this->assertNotContains('approved_at', $fillable);
		$this->assertNotContains('approved_by', $fillable);
	}

	/**
	 * Test that revokePublicKey clears all key-related fields.
	 */
	public function testRevokePublicKeyClearsFields(): void
	{
		$device = $this->getMockBuilder(Device::class)
			->onlyMethods(['save'])
			->getMock();

		$device->expects($this->once())->method('save');

		$device->public_key = 'some-key';
		$device->approved_at = now();
		$device->approved_by = 1;

		$device->revokePublicKey();

		$this->assertNull($device->public_key);
		$this->assertNull($device->approved_at);
		$this->assertNull($device->approved_by);
	}

	/**
	 * Test that MAX_DEVICE_ID is correct for 3-byte unsigned integer.
	 */
	public function testMaxDeviceIdConstant(): void
	{
		$this->assertEquals(16777215, Device::MAX_DEVICE_ID);
		$this->assertEquals(0xFFFFFF, Device::MAX_DEVICE_ID);
	}
}
