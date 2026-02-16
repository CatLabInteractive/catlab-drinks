<?php

namespace Tests\Unit;

use App\Models\OrganisationPaymentGateway;
use PHPUnit\Framework\TestCase;

/**
 * Tests for credential encryption, validation, and accessor logic.
 * Uses a partial mock to test credential logic without a database.
 */
class OrganisationPaymentGatewayCredentialsTest extends TestCase
{
    /**
     * Test hasValidCredentials returns false when gateway has no credentials set.
     */
    public function testHasValidCredentialsReturnsFalseWithEmptyCredentials(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods([])
            ->getMock();

        $gateway->gateway = OrganisationPaymentGateway::GATEWAY_PAYNL;

        // Simulate empty credentials by overriding the attribute accessor
        $gateway->setAttribute('gateway', OrganisationPaymentGateway::GATEWAY_PAYNL);

        // Without credentials set, the raw attribute will be empty
        $this->assertFalse($gateway->hasValidCredentials());
    }

    /**
     * Test getCredential returns default when credentials are empty.
     */
    public function testGetCredentialReturnsDefaultWhenEmpty(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods([])
            ->getMock();

        $gateway->gateway = OrganisationPaymentGateway::GATEWAY_PAYNL;

        $this->assertNull($gateway->getCredential('apiToken'));
        $this->assertEquals('default_value', $gateway->getCredential('apiToken', 'default_value'));
    }

    /**
     * Test hasValidCredentials with correct mock for PayNL gateway.
     */
    public function testHasValidCredentialsWithAllCredentialsSet(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->gateway = OrganisationPaymentGateway::GATEWAY_PAYNL;

        $gateway->method('getCredentialsAttribute')
            ->willReturn([
                'apiToken' => 'test-token',
                'apiSecret' => 'test-secret',
                'serviceId' => 'SL-1234-5678',
            ]);

        $this->assertTrue($gateway->hasValidCredentials());
    }

    /**
     * Test hasValidCredentials returns false when one credential is missing.
     */
    public function testHasValidCredentialsReturnsFalseWithMissingCredential(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->gateway = OrganisationPaymentGateway::GATEWAY_PAYNL;

        $gateway->method('getCredentialsAttribute')
            ->willReturn([
                'apiToken' => 'test-token',
                'apiSecret' => 'test-secret',
                // serviceId is missing
            ]);

        $this->assertFalse($gateway->hasValidCredentials());
    }

    /**
     * Test hasValidCredentials returns false when credential is empty string.
     */
    public function testHasValidCredentialsReturnsFalseWithEmptyCredentialValue(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->gateway = OrganisationPaymentGateway::GATEWAY_PAYNL;

        $gateway->method('getCredentialsAttribute')
            ->willReturn([
                'apiToken' => 'test-token',
                'apiSecret' => '',
                'serviceId' => 'SL-1234-5678',
            ]);

        $this->assertFalse($gateway->hasValidCredentials());
    }

    /**
     * Test getCredential with mocked credentials.
     */
    public function testGetCredentialReturnsCorrectValue(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->method('getCredentialsAttribute')
            ->willReturn([
                'apiToken' => 'my-token',
                'apiSecret' => 'my-secret',
                'serviceId' => 'SL-1234',
            ]);

        $this->assertEquals('my-token', $gateway->getCredential('apiToken'));
        $this->assertEquals('my-secret', $gateway->getCredential('apiSecret'));
        $this->assertEquals('SL-1234', $gateway->getCredential('serviceId'));
    }

    /**
     * Test getCredential returns default for non-existent key.
     */
    public function testGetCredentialReturnsDefaultForMissingKey(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->method('getCredentialsAttribute')
            ->willReturn([
                'apiToken' => 'my-token',
            ]);

        $this->assertNull($gateway->getCredential('nonExistentKey'));
        $this->assertEquals('fallback', $gateway->getCredential('nonExistentKey', 'fallback'));
    }

    /**
     * Test hasValidCredentials returns true for unknown gateway (no required credentials).
     */
    public function testHasValidCredentialsReturnsTrueForUnknownGateway(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->gateway = 'unknown_gateway';

        $gateway->method('getCredentialsAttribute')
            ->willReturn([]);

        $this->assertTrue($gateway->hasValidCredentials());
    }

    /**
     * Test that the has_valid_credentials accessor works.
     */
    public function testHasValidCredentialsAttribute(): void
    {
        $gateway = $this->getMockBuilder(OrganisationPaymentGateway::class)
            ->onlyMethods(['getCredentialsAttribute'])
            ->getMock();

        $gateway->gateway = OrganisationPaymentGateway::GATEWAY_PAYNL;

        $gateway->method('getCredentialsAttribute')
            ->willReturn([
                'apiToken' => 'test-token',
                'apiSecret' => 'test-secret',
                'serviceId' => 'SL-1234-5678',
            ]);

        $this->assertTrue($gateway->getHasValidCredentialsAttribute());
    }
}
