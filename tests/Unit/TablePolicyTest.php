<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Table;
use App\Models\User;
use App\Policies\TablePolicy;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TablePolicy authorization logic.
 *
 * TablePolicy allows device access for index, create, view, and edit.
 * Only destroy is restricted to users (no device access).
 */
class TablePolicyTest extends TestCase
{
	private TablePolicy $policy;

	protected function setUp(): void
	{
		parent::setUp();
		$this->policy = new TablePolicy();
	}

	/**
	 * Helper to create a mock Event that belongs to an Organisation.
	 */
	private function createMockEvent(Organisation $organisation): Event
	{
		$event = $this->createMock(Event::class);
		$event->method('__get')
			->willReturnMap([
				['organisation', $organisation],
			]);
		return $event;
	}

	/**
	 * Helper to create a mock Table that belongs to an Event.
	 */
	private function createMockTable(Event $event): Table
	{
		$table = $this->createMock(Table::class);
		$table->method('__get')
			->willReturnMap([
				['event', $event],
			]);
		return $table;
	}

	/**
	 * Helper to create a mock User in the given organisation.
	 */
	private function createMockUser(Organisation $organisation): User
	{
		$user = $this->createMock(User::class);
		$collection = new Collection([$organisation]);
		$user->method('__get')
			->with('organisations')
			->willReturn($collection);
		return $user;
	}

	/**
	 * Helper to create a mock Device in the given organisation.
	 */
	private function createMockDevice(Organisation $organisation): Device
	{
		$device = $this->createMock(Device::class);
		$device->method('__get')
			->willReturnMap([
				['organisation_id', $organisation->id],
			]);
		return $device;
	}

	// --- index tests ---

	public function testIndexAllowedForOrganisationUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$user = $this->createMockUser($org);

		$this->assertTrue($this->policy->index($user, $event));
	}

	public function testIndexAllowedForDeviceInOrganisation(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$device = $this->createMockDevice($org);

		$this->assertTrue($this->policy->index($device, $event));
	}

	public function testIndexDeniedForDeviceInDifferentOrganisation(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org1);
		$device = $this->createMockDevice($org2);

		$this->assertFalse($this->policy->index($device, $event));
	}

	public function testIndexDeniedForNonMemberUser(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org2);
		$user = $this->createMockUser($org1);

		$this->assertFalse($this->policy->index($user, $event));
	}

	public function testIndexDeniedForNullUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);

		$this->assertFalse($this->policy->index(null, $event));
	}

	// --- create tests ---

	public function testCreateAllowedForOrganisationUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$user = $this->createMockUser($org);

		$this->assertTrue($this->policy->create($user, $event));
	}

	public function testCreateAllowedForDeviceInOrganisation(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$device = $this->createMockDevice($org);

		$this->assertTrue($this->policy->create($device, $event));
	}

	public function testCreateDeniedForNullUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);

		$this->assertFalse($this->policy->create(null, $event));
	}

	// --- view tests ---

	public function testViewAllowedForOrganisationUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);
		$user = $this->createMockUser($org);

		$this->assertTrue($this->policy->view($user, $table));
	}

	public function testViewAllowedForDeviceInOrganisation(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);
		$device = $this->createMockDevice($org);

		$this->assertTrue($this->policy->view($device, $table));
	}

	public function testViewDeniedForNonMemberUser(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org2);
		$table = $this->createMockTable($event);
		$user = $this->createMockUser($org1);

		$this->assertFalse($this->policy->view($user, $table));
	}

	public function testViewDeniedForNullUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);

		$this->assertFalse($this->policy->view(null, $table));
	}

	// --- edit tests ---

	public function testEditAllowedForOrganisationUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);
		$user = $this->createMockUser($org);

		$this->assertTrue($this->policy->edit($user, $table));
	}

	public function testEditAllowedForDeviceInOrganisation(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);
		$device = $this->createMockDevice($org);

		$this->assertTrue($this->policy->edit($device, $table));
	}

	public function testEditDeniedForNonMemberUser(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org2);
		$table = $this->createMockTable($event);
		$user = $this->createMockUser($org1);

		$this->assertFalse($this->policy->edit($user, $table));
	}

	public function testEditDeniedForNullUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);

		$this->assertFalse($this->policy->edit(null, $table));
	}

	// --- destroy tests ---

	public function testDestroyAllowedForOrganisationUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);
		$user = $this->createMockUser($org);

		$this->assertTrue($this->policy->destroy($user, $table));
	}

	/**
	 * Destroy is restricted: no device access allowed.
	 */
	public function testDestroyDeniedForDevice(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);
		$device = $this->createMockDevice($org);

		$this->assertFalse($this->policy->destroy($device, $table));
	}

	public function testDestroyDeniedForNonMemberUser(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;
		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org2);
		$table = $this->createMockTable($event);
		$user = $this->createMockUser($org1);

		$this->assertFalse($this->policy->destroy($user, $table));
	}

	public function testDestroyDeniedForNullUser(): void
	{
		$org = new Organisation();
		$org->id = 1;

		$event = $this->createMockEvent($org);
		$table = $this->createMockTable($event);

		$this->assertFalse($this->policy->destroy(null, $table));
	}
}
