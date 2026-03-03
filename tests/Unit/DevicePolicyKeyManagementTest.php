<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\DevicePolicy;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the new DevicePolicy key management authorization methods.
 */
class DevicePolicyKeyManagementTest extends TestCase
{
	private DevicePolicy $policy;

	protected function setUp(): void
	{
		parent::setUp();
		$this->policy = new DevicePolicy();
	}

	/**
	 * Helper to create a mock user in an organisation.
	 */
	private function createUserInOrganisation(Organisation $organisation): User
	{
		$user = $this->createMock(User::class);
		$collection = new Collection([$organisation]);
		$user->method('__get')
			->with('organisations')
			->willReturn($collection);
		return $user;
	}

	/**
	 * Helper to create a mock device in an organisation.
	 */
	private function createDeviceInOrganisation(Organisation $organisation, int $deviceId = 10): Device
	{
		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['id', $deviceId],
				['organisation', $organisation],
			]);
		return $device;
	}

	// --- approveKey ---

	public function testApproveKeyAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$user = $this->createUserInOrganisation($organisation);
		$device = $this->createDeviceInOrganisation($organisation);

		$this->assertTrue($this->policy->approveKey($user, $device));
	}

	public function testApproveKeyDeniedForNonMember(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$user = $this->createUserInOrganisation($org1);
		$device = $this->createDeviceInOrganisation($org2);

		$this->assertFalse($this->policy->approveKey($user, $device));
	}

	public function testApproveKeyDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$device = $this->createDeviceInOrganisation($organisation);

		$this->assertFalse($this->policy->approveKey(null, $device));
	}

	public function testApproveKeyDeniedForDevice(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$device = $this->createMock(Device::class);
		$device->id = 42;
		$device->method('__get')
			->willReturnMap([
				['id', 42],
				['organisation', $organisation],
				['organisation_id', 1],
			]);

		$targetDevice = $this->createDeviceInOrganisation($organisation);

		$this->assertFalse($this->policy->approveKey($device, $targetDevice));
	}

	// --- revokeKey ---

	public function testRevokeKeyAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$user = $this->createUserInOrganisation($organisation);
		$device = $this->createDeviceInOrganisation($organisation);

		$this->assertTrue($this->policy->revokeKey($user, $device));
	}

	public function testRevokeKeyDeniedForNonMember(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$user = $this->createUserInOrganisation($org1);
		$device = $this->createDeviceInOrganisation($org2);

		$this->assertFalse($this->policy->revokeKey($user, $device));
	}

	public function testRevokeKeyDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$device = $this->createDeviceInOrganisation($organisation);

		$this->assertFalse($this->policy->revokeKey(null, $device));
	}

	// --- viewPublicKeys ---

	public function testViewPublicKeysAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$user = $this->createUserInOrganisation($organisation);

		$this->assertTrue($this->policy->viewPublicKeys($user, $organisation));
	}

	public function testViewPublicKeysAllowedForDeviceInOrganisation(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['organisation_id', 1],
			]);

		$this->assertTrue($this->policy->viewPublicKeys($device, $organisation));
	}

	public function testViewPublicKeysDeniedForDeviceInDifferentOrganisation(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;

		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['organisation_id', 2],
			]);

		$this->assertFalse($this->policy->viewPublicKeys($device, $org1));
	}

	public function testViewPublicKeysAllowedWithoutOrganisation(): void
	{
		$user = $this->createMock(User::class);
		$this->assertTrue($this->policy->viewPublicKeys($user));
	}

	public function testViewPublicKeysDeniedForNullUserWithOrganisation(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$this->assertFalse($this->policy->viewPublicKeys(null, $organisation));
	}
}
