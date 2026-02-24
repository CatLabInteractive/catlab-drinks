<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\DevicePolicy;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DevicePolicy authorization logic.
 */
class DevicePolicyTest extends TestCase
{
	private DevicePolicy $policy;

	protected function setUp(): void
	{
		parent::setUp();
		$this->policy = new DevicePolicy();
	}

	/**
	 * Test that a device can view itself.
	 */
	public function testViewAllowedForSameDevice(): void
	{
		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$targetDevice = $this->createMock(Device::class);
		$targetDevice->id = 42;
		$targetDevice->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$this->assertTrue($this->policy->view($device, $targetDevice));
	}

	/**
	 * Test that a device cannot view a different device.
	 */
	public function testViewDeniedForDifferentDevice(): void
	{
		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$targetDevice = $this->createMock(Device::class);
		$targetDevice->id = 99;
		$targetDevice->method('__get')
			->willReturnMap([
				['id', 99],
			]);

		$this->assertFalse($this->policy->view($device, $targetDevice));
	}

	/**
	 * Test that a device can edit itself.
	 */
	public function testEditAllowedForSameDevice(): void
	{
		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$targetDevice = $this->createMock(Device::class);
		$targetDevice->id = 42;
		$targetDevice->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$this->assertTrue($this->policy->edit($device, $targetDevice));
	}

	/**
	 * Test that a device cannot edit a different device.
	 */
	public function testEditDeniedForDifferentDevice(): void
	{
		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$targetDevice = $this->createMock(Device::class);
		$targetDevice->id = 99;
		$targetDevice->method('__get')
			->willReturnMap([
				['id', 99],
				['organisation', new Organisation()],
			]);

		$this->assertFalse($this->policy->edit($device, $targetDevice));
	}

	/**
	 * Test that an organisation user can view a device in their org.
	 */
	public function testViewAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$user = $this->createMock(User::class);
		$collection = new Collection([$organisation]);
		$user->method('__get')
			->with('organisations')
			->willReturn($collection);

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['id', 10],
				['organisation', $organisation],
			]);

		$this->assertTrue($this->policy->view($user, $device));
	}

	/**
	 * Test that view is denied for a user not in the org.
	 */
	public function testViewDeniedForNonMember(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;

		$org2 = new Organisation();
		$org2->id = 2;

		$user = $this->createMock(User::class);
		$collection = new Collection([$org1]);
		$user->method('__get')
			->with('organisations')
			->willReturn($collection);

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['id', 10],
				['organisation', $org2],
			]);

		$this->assertFalse($this->policy->view($user, $device));
	}

	/**
	 * Test that an organisation user can edit a device in their org.
	 */
	public function testEditAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$user = $this->createMock(User::class);
		$collection = new Collection([$organisation]);
		$user->method('__get')
			->with('organisations')
			->willReturn($collection);

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['id', 10],
				['organisation', $organisation],
			]);

		$this->assertTrue($this->policy->edit($user, $device));
	}

	/**
	 * Test that edit is denied for null user.
	 */
	public function testEditDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['id', 10],
				['organisation', $organisation],
			]);

		$this->assertFalse($this->policy->edit(null, $device));
	}

	/**
	 * Test that view is denied for null user.
	 */
	public function testViewDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['id', 10],
				['organisation', $organisation],
			]);

		$this->assertFalse($this->policy->view(null, $device));
	}

	/**
	 * Test that index is always allowed.
	 */
	public function testIndexAlwaysAllowed(): void
	{
		$user = $this->createMock(User::class);
		$this->assertTrue($this->policy->index($user));
		$this->assertTrue($this->policy->index(null));
	}

	/**
	 * Test that create is always denied.
	 */
	public function testCreateAlwaysDenied(): void
	{
		$user = $this->createMock(User::class);
		$this->assertFalse($this->policy->create($user));
	}

	/**
	 * Test that viewSecret is allowed for same device.
	 */
	public function testViewSecretAllowedForSameDevice(): void
	{
		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$targetDevice = $this->createMock(Device::class);
		$targetDevice->id = 42;
		$targetDevice->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$this->assertTrue($this->policy->viewSecret($device, $targetDevice));
	}

	/**
	 * Test that viewSecret is denied for user (not a device).
	 */
	public function testViewSecretDeniedForUser(): void
	{
		$user = $this->createMock(User::class);

		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
			]);

		$this->assertFalse($this->policy->viewSecret($user, $device));
	}
}
