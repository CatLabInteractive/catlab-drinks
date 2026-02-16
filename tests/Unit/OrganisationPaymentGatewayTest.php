<?php

namespace Tests\Unit;

use App\Models\OrganisationPaymentGateway;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OrganisationPaymentGateway model logic.
 */
class OrganisationPaymentGatewayTest extends TestCase
{
    /**
     * Test that getRequiredCredentials returns correct keys for PayNL.
     */
    public function testGetRequiredCredentialsForPaynl(): void
    {
        $required = OrganisationPaymentGateway::getRequiredCredentials(OrganisationPaymentGateway::GATEWAY_PAYNL);

        $this->assertIsArray($required);
        $this->assertContains('apiToken', $required);
        $this->assertContains('apiSecret', $required);
        $this->assertContains('serviceId', $required);
        $this->assertCount(3, $required);
    }

    /**
     * Test that getRequiredCredentials returns empty array for unknown gateway.
     */
    public function testGetRequiredCredentialsForUnknownGateway(): void
    {
        $required = OrganisationPaymentGateway::getRequiredCredentials('unknown_gateway');

        $this->assertIsArray($required);
        $this->assertEmpty($required);
    }

    /**
     * Test that GATEWAY_PAYNL constant is defined correctly.
     */
    public function testGatewayPaynlConstant(): void
    {
        $this->assertEquals('paynl', OrganisationPaymentGateway::GATEWAY_PAYNL);
    }

    /**
     * Test that fillable attributes are correctly defined.
     */
    public function testFillableAttributes(): void
    {
        $gateway = new OrganisationPaymentGateway();
        $fillable = $gateway->getFillable();

        $this->assertContains('gateway', $fillable);
        $this->assertContains('is_testing', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertNotContains('credentials', $fillable);
    }

    /**
     * Test that boolean casts are correctly defined.
     */
    public function testCasts(): void
    {
        $gateway = new OrganisationPaymentGateway();
        $casts = $gateway->getCasts();

        $this->assertArrayHasKey('is_testing', $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertEquals('boolean', $casts['is_testing']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    /**
     * Test that the table name is correctly set.
     */
    public function testTableName(): void
    {
        $gateway = new OrganisationPaymentGateway();
        $this->assertEquals('organisation_payment_gateways', $gateway->getTable());
    }
}
