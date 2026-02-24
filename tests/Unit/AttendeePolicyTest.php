<?php

namespace Tests\Unit;

use App\Models\Attendee;
use App\Models\Device;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\AttendeePolicy;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AttendeePolicy authorization logic.
 */
class AttendeePolicyTest extends TestCase
{
	private AttendeePolicy $policy;

	protected function setUp(): void
	{
		parent::setUp();
		$this->policy = new AttendeePolicy();
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
	 * Helper to create a mock Attendee that belongs to an Event.
	 */
	private function createMockAttendee(Event $event): Attendee
	{
		$attendee = $this->createMock(Attendee::class);
		$attendee->method('__get')
			->willReturnMap([
				['event', $event],
			]);
		return $attendee;
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

	/**
	 * Test that index is allowed for a user in the event's organisation.
	 */
	public function testIndexAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$user = $this->createMockUser($organisation);

		$this->assertTrue($this->policy->index($user, $event));
	}

	/**
	 * Test that index is denied for a user not in the event's organisation.
	 */
	public function testIndexDeniedForNonMember(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;

		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org2);
		$user = $this->createMockUser($org1);

		$this->assertFalse($this->policy->index($user, $event));
	}

	/**
	 * Test that index is allowed for a device in the event's organisation.
	 */
	public function testIndexAllowedForDeviceInOrganisation(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$device = $this->createMockDevice($organisation);

		$this->assertTrue($this->policy->index($device, $event));
	}

	/**
	 * Test that index is denied for a device in a different organisation.
	 */
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

	/**
	 * Test that index is denied for null user.
	 */
	public function testIndexDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);

		$this->assertFalse($this->policy->index(null, $event));
	}

	// --- create tests ---

	/**
	 * Test that create is allowed for a user in the event's organisation.
	 */
	public function testCreateAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$user = $this->createMockUser($organisation);

		$this->assertTrue($this->policy->create($user, $event));
	}

	/**
	 * Test that create is denied for a device (no device access for write operations).
	 */
	public function testCreateDeniedForDevice(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$device = $this->createMockDevice($organisation);

		$this->assertFalse($this->policy->create($device, $event));
	}

	/**
	 * Test that create is denied for null user.
	 */
	public function testCreateDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);

		$this->assertFalse($this->policy->create(null, $event));
	}

	// --- view tests ---

	/**
	 * Test that view is allowed for a user in the attendee's event organisation.
	 */
	public function testViewAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);
		$user = $this->createMockUser($organisation);

		$this->assertTrue($this->policy->view($user, $attendee));
	}

	/**
	 * Test that view is denied for a device (no device access for view).
	 */
	public function testViewDeniedForDevice(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);
		$device = $this->createMockDevice($organisation);

		$this->assertFalse($this->policy->view($device, $attendee));
	}

	/**
	 * Test that view is denied for null user.
	 */
	public function testViewDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);

		$this->assertFalse($this->policy->view(null, $attendee));
	}

	// --- edit tests ---

	/**
	 * Test that edit is allowed for a user in the attendee's event organisation.
	 */
	public function testEditAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);
		$user = $this->createMockUser($organisation);

		$this->assertTrue($this->policy->edit($user, $attendee));
	}

	/**
	 * Test that edit is denied for a device (no device access for edit).
	 */
	public function testEditDeniedForDevice(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);
		$device = $this->createMockDevice($organisation);

		$this->assertFalse($this->policy->edit($device, $attendee));
	}

	/**
	 * Test that edit is denied for null user.
	 */
	public function testEditDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);

		$this->assertFalse($this->policy->edit(null, $attendee));
	}

	// --- destroy tests ---

	/**
	 * Test that destroy is allowed for a user in the attendee's event organisation.
	 */
	public function testDestroyAllowedForOrganisationMember(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);
		$user = $this->createMockUser($organisation);

		$this->assertTrue($this->policy->destroy($user, $attendee));
	}

	/**
	 * Test that destroy is denied for non-member.
	 */
	public function testDestroyDeniedForNonMember(): void
	{
		$org1 = new Organisation();
		$org1->id = 1;

		$org2 = new Organisation();
		$org2->id = 2;

		$event = $this->createMockEvent($org2);
		$attendee = $this->createMockAttendee($event);
		$user = $this->createMockUser($org1);

		$this->assertFalse($this->policy->destroy($user, $attendee));
	}

	/**
	 * Test that destroy is denied for null user.
	 */
	public function testDestroyDeniedForNullUser(): void
	{
		$organisation = new Organisation();
		$organisation->id = 1;

		$event = $this->createMockEvent($organisation);
		$attendee = $this->createMockAttendee($event);

		$this->assertFalse($this->policy->destroy(null, $attendee));
	}
}
