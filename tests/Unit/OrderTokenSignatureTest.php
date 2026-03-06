<?php

namespace Tests\Unit;

use App\Services\OrderTokenSignatureService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OrderTokenSignatureService.
 */
class OrderTokenSignatureTest extends TestCase
{
    /**
     * Test that signing with card parameter produces a valid signature.
     */
    public function testSignWithCardParam(): void
    {
        $secret = 'test-secret-123';
        $params = ['card' => 'abcdef'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertNotEmpty($signature);
        $this->assertTrue(OrderTokenSignatureService::verify($secret, $params, $signature));
    }

    /**
     * Test that signing with name parameter produces a valid signature.
     */
    public function testSignWithNameParam(): void
    {
        $secret = 'test-secret-123';
        $params = ['name' => 'John Doe'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertNotEmpty($signature);
        $this->assertTrue(OrderTokenSignatureService::verify($secret, $params, $signature));
    }

    /**
     * Test that signing with both card and name produces a valid signature.
     */
    public function testSignWithBothParams(): void
    {
        $secret = 'test-secret-123';
        $params = ['card' => 'abcdef', 'name' => 'John Doe'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertNotEmpty($signature);
        $this->assertTrue(OrderTokenSignatureService::verify($secret, $params, $signature));
    }

    /**
     * Test that verification fails with wrong signature.
     */
    public function testVerifyFailsWithWrongSignature(): void
    {
        $secret = 'test-secret-123';
        $params = ['card' => 'abcdef'];

        $this->assertFalse(OrderTokenSignatureService::verify($secret, $params, 'wrong-signature'));
    }

    /**
     * Test that verification fails with wrong secret.
     */
    public function testVerifyFailsWithWrongSecret(): void
    {
        $secret = 'test-secret-123';
        $params = ['card' => 'abcdef'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertFalse(OrderTokenSignatureService::verify('wrong-secret', $params, $signature));
    }

    /**
     * Test that verification fails with modified parameters.
     */
    public function testVerifyFailsWithModifiedParams(): void
    {
        $secret = 'test-secret-123';
        $params = ['card' => 'abcdef'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $modifiedParams = ['card' => 'xyz123'];
        $this->assertFalse(OrderTokenSignatureService::verify($secret, $modifiedParams, $signature));
    }

    /**
     * Test that signing with no signable parameters returns empty string.
     */
    public function testSignWithNoSignableParams(): void
    {
        $secret = 'test-secret-123';
        $params = ['foo' => 'bar', 'baz' => 'qux'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertEquals('', $signature);
    }

    /**
     * Test that non-signable parameters are ignored.
     */
    public function testNonSignableParamsIgnored(): void
    {
        $secret = 'test-secret-123';
        $params1 = ['card' => 'abcdef', 'extra' => 'ignored'];
        $params2 = ['card' => 'abcdef'];

        $sig1 = OrderTokenSignatureService::sign($secret, $params1);
        $sig2 = OrderTokenSignatureService::sign($secret, $params2);

        $this->assertEquals($sig1, $sig2);
    }

    /**
     * Test that parameter order does not affect signature (alphabetically sorted).
     */
    public function testParameterOrderDoesNotMatter(): void
    {
        $secret = 'test-secret-123';
        $params1 = ['name' => 'John', 'card' => 'abcdef'];
        $params2 = ['card' => 'abcdef', 'name' => 'John'];

        $sig1 = OrderTokenSignatureService::sign($secret, $params1);
        $sig2 = OrderTokenSignatureService::sign($secret, $params2);

        $this->assertEquals($sig1, $sig2);
    }

    /**
     * Test hasSignableParams returns true when signable params exist.
     */
    public function testHasSignableParamsTrue(): void
    {
        $this->assertTrue(OrderTokenSignatureService::hasSignableParams(['card' => 'abc']));
        $this->assertTrue(OrderTokenSignatureService::hasSignableParams(['name' => 'John']));
        $this->assertTrue(OrderTokenSignatureService::hasSignableParams(['card' => 'abc', 'name' => 'John']));
    }

    /**
     * Test hasSignableParams returns false when no signable params exist.
     */
    public function testHasSignableParamsFalse(): void
    {
        $this->assertFalse(OrderTokenSignatureService::hasSignableParams([]));
        $this->assertFalse(OrderTokenSignatureService::hasSignableParams(['foo' => 'bar']));
        $this->assertFalse(OrderTokenSignatureService::hasSignableParams(['card' => '']));
        $this->assertFalse(OrderTokenSignatureService::hasSignableParams(['card' => null]));
    }

    /**
     * Test that verify rejects empty signature.
     */
    public function testVerifyRejectsEmptySignature(): void
    {
        $secret = 'test-secret-123';
        $params = ['card' => 'abcdef'];

        $this->assertFalse(OrderTokenSignatureService::verify($secret, $params, ''));
    }

    /**
     * Test signature is deterministic.
     */
    public function testSignatureIsDeterministic(): void
    {
        $secret = 'my-secret';
        $params = ['card' => 'player1', 'name' => 'Alice'];

        $sig1 = OrderTokenSignatureService::sign($secret, $params);
        $sig2 = OrderTokenSignatureService::sign($secret, $params);

        $this->assertEquals($sig1, $sig2);
    }

    /**
     * Test signature format is hex-encoded HMAC-SHA256 (64 chars).
     */
    public function testSignatureFormat(): void
    {
        $secret = 'test-secret';
        $params = ['card' => 'abc'];

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertEquals(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
    }

    /**
     * Test known signature value for interoperability verification.
     * This ensures the algorithm produces consistent results that
     * can be verified by third-party implementations.
     */
    public function testKnownSignatureValue(): void
    {
        $secret = 'mysecret';
        $params = ['card' => 'player1', 'name' => 'Alice'];

        // The message to sign is: "card=player1&name=Alice" (alphabetically sorted, URL-encoded)
        $expectedSignature = hash_hmac('sha256', 'card=player1&name=Alice', 'mysecret');

        $signature = OrderTokenSignatureService::sign($secret, $params);

        $this->assertEquals($expectedSignature, $signature);
    }

    /**
     * Test that URL encoding prevents parameter injection/ambiguity.
     * A card value containing special characters should produce a different
     * signature than legitimate separate parameters.
     */
    public function testUrlEncodingPreventsAmbiguity(): void
    {
        $secret = 'test-secret';

        // card="a&name=b" (single param with special chars)
        $params1 = ['card' => 'a&name=b'];
        $sig1 = OrderTokenSignatureService::sign($secret, $params1);

        // card="a", name="b" (two separate params)
        $params2 = ['card' => 'a', 'name' => 'b'];
        $sig2 = OrderTokenSignatureService::sign($secret, $params2);

        // These MUST produce different signatures
        $this->assertNotEquals($sig1, $sig2);
    }
}
