<?php

namespace Tests\Unit;

use App\Models\Organisation;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Organisation model methods.
 */
class OrganisationModelTest extends TestCase
{
	/**
	 * Test that devices relationship method exists.
	 */
	public function testDevicesRelationshipExists(): void
	{
		$organisation = new Organisation();
		$this->assertTrue(method_exists($organisation, 'devices'));
	}

	/**
	 * Test that allDevices relationship method exists (includes soft-deleted).
	 */
	public function testAllDevicesRelationshipExists(): void
	{
		$organisation = new Organisation();
		$this->assertTrue(method_exists($organisation, 'allDevices'));
	}

	/**
	 * Test that approvedDevicesWithKeys relationship method exists.
	 */
	public function testApprovedDevicesWithKeysRelationshipExists(): void
	{
		$organisation = new Organisation();
		$this->assertTrue(method_exists($organisation, 'approvedDevicesWithKeys'));
	}

	/**
	 * Test that fillable attributes are correct.
	 */
	public function testFillableAttributes(): void
	{
		$organisation = new Organisation();
		$fillable = $organisation->getFillable();

		$this->assertContains('name', $fillable);
	}
}
