<?php

namespace Tests\Unit;

use App\Models\Organisation;
use App\Models\OrganisationPaymentGateway;
use App\Models\User;
use App\Policies\OrganisationPaymentGatewayPolicy;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OrganisationPaymentGatewayPolicy authorization logic.
 */
class OrganisationPaymentGatewayPolicyTest extends TestCase
{
    private OrganisationPaymentGatewayPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new OrganisationPaymentGatewayPolicy();
    }

    /**
     * Test that index is only allowed for members of the organisation.
     */
    public function testIndexRequiresOrganisationMembership(): void
    {
        $organisation = new Organisation();
        $organisation->id = 1;

        $otherOrganisation = new Organisation();
        $otherOrganisation->id = 2;

        $user = $this->createMock(User::class);
        $user->method('__get')
            ->with('organisations')
            ->willReturn(new Collection([$organisation]));

        $this->assertTrue($this->policy->index($user, $organisation));
        $this->assertFalse($this->policy->index($user, $otherOrganisation));
    }

    /**
     * Test that index is denied for null user.
     */
    public function testIndexDeniedForNullUser(): void
    {
        $organisation = new Organisation();
        $organisation->id = 1;

        $this->assertFalse($this->policy->index(null, $organisation));
    }

    /**
     * Test that create is only allowed for members of the organisation.
     */
    public function testCreateRequiresOrganisationMembership(): void
    {
        $organisation = new Organisation();
        $organisation->id = 1;

        $otherOrganisation = new Organisation();
        $otherOrganisation->id = 2;

        $user = $this->createMock(User::class);
        $user->method('__get')
            ->with('organisations')
            ->willReturn(new Collection([$organisation]));

        $this->assertTrue($this->policy->create($user, $organisation));
        $this->assertFalse($this->policy->create($user, $otherOrganisation));
    }

    /**
     * Test that view is allowed for user in the same organisation.
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

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation);

        $this->assertTrue($this->policy->view($user, $gateway));
    }

    /**
     * Test that view is denied for user in different organisation.
     */
    public function testViewDeniedForNonMember(): void
    {
        $organisation1 = new Organisation();
        $organisation1->id = 1;

        $organisation2 = new Organisation();
        $organisation2->id = 2;

        $user = $this->createMock(User::class);
        $collection = new Collection([$organisation1]);
        $user->method('__get')
            ->with('organisations')
            ->willReturn($collection);

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation2);

        $this->assertFalse($this->policy->view($user, $gateway));
    }

    /**
     * Test that view is denied for null user.
     */
    public function testViewDeniedForNullUser(): void
    {
        $organisation = new Organisation();
        $organisation->id = 1;

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation);

        $this->assertFalse($this->policy->view(null, $gateway));
    }

    /**
     * Test that edit is allowed for organisation member.
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

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation);

        $this->assertTrue($this->policy->edit($user, $gateway));
    }

    /**
     * Test that edit is denied for non-member.
     */
    public function testEditDeniedForNonMember(): void
    {
        $organisation1 = new Organisation();
        $organisation1->id = 1;

        $organisation2 = new Organisation();
        $organisation2->id = 2;

        $user = $this->createMock(User::class);
        $collection = new Collection([$organisation1]);
        $user->method('__get')
            ->with('organisations')
            ->willReturn($collection);

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation2);

        $this->assertFalse($this->policy->edit($user, $gateway));
    }

    /**
     * Test that destroy is allowed for organisation member.
     */
    public function testDestroyAllowedForOrganisationMember(): void
    {
        $organisation = new Organisation();
        $organisation->id = 1;

        $user = $this->createMock(User::class);
        $collection = new Collection([$organisation]);
        $user->method('__get')
            ->with('organisations')
            ->willReturn($collection);

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation);

        $this->assertTrue($this->policy->destroy($user, $gateway));
    }

    /**
     * Test that destroy is denied for non-member.
     */
    public function testDestroyDeniedForNonMember(): void
    {
        $organisation1 = new Organisation();
        $organisation1->id = 1;

        $organisation2 = new Organisation();
        $organisation2->id = 2;

        $user = $this->createMock(User::class);
        $collection = new Collection([$organisation1]);
        $user->method('__get')
            ->with('organisations')
            ->willReturn($collection);

        $gateway = $this->createMock(OrganisationPaymentGateway::class);
        $gateway->method('__get')
            ->with('organisation')
            ->willReturn($organisation2);

        $this->assertFalse($this->policy->destroy($user, $gateway));
    }
}
