<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organisation;
use App\Services\OrderTokenSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for signed remote order URL functionality.
 */
class SignedOrderUrlTest extends TestCase
{
    use RefreshDatabase;

    private function createEventWithSecret(): Event
    {
        $organisation = Organisation::factory()->create();

        return Event::factory()->create([
            'organisation_id' => $organisation->id,
            'order_token' => 'testpublictoken123456789012',
            'order_token_secret' => 'testsecret12345678901234567890',
        ]);
    }

    private function createEventWithoutSecret(): Event
    {
        $organisation = Organisation::factory()->create();

        return Event::factory()->create([
            'organisation_id' => $organisation->id,
            'order_token' => 'legacytoken1234567890123456',
            'order_token_secret' => null,
        ]);
    }

    /**
     * Test that order page loads without params (no signature needed).
     */
    public function testOrderPageLoadsWithoutParams(): void
    {
        $event = $this->createEventWithSecret();

        $response = $this->get('/order/' . $event->order_token);

        $response->assertStatus(200);
    }

    /**
     * Test that order page loads with valid signature.
     */
    public function testOrderPageLoadsWithValidSignature(): void
    {
        $event = $this->createEventWithSecret();

        $params = ['card' => 'abcdef'];
        $signature = OrderTokenSignatureService::sign($event->order_token_secret, $params);

        $response = $this->get('/order/' . $event->order_token . '?card=abcdef&signature=' . $signature);

        $response->assertStatus(200);
    }

    /**
     * Test that order page rejects invalid signature.
     */
    public function testOrderPageRejectsInvalidSignature(): void
    {
        $event = $this->createEventWithSecret();

        $response = $this->get('/order/' . $event->order_token . '?card=abcdef&signature=invalidsig');

        $response->assertStatus(403);
    }

    /**
     * Test that order page rejects missing signature when params present and secret exists.
     */
    public function testOrderPageRejectsMissingSignature(): void
    {
        $event = $this->createEventWithSecret();

        $response = $this->get('/order/' . $event->order_token . '?card=abcdef');

        $response->assertStatus(403);
    }

    /**
     * Test that legacy events (no secret) allow unsigned params.
     */
    public function testLegacyEventAllowsUnsignedParams(): void
    {
        $event = $this->createEventWithoutSecret();

        $response = $this->get('/order/' . $event->order_token . '?card=abcdef');

        $response->assertStatus(200);
    }

    /**
     * Test that order page loads with both card and name signed.
     */
    public function testOrderPageWithBothParamsSigned(): void
    {
        $event = $this->createEventWithSecret();

        $params = ['card' => 'player1', 'name' => 'Alice'];
        $signature = OrderTokenSignatureService::sign($event->order_token_secret, $params);

        $response = $this->get('/order/' . $event->order_token . '?card=player1&name=Alice&signature=' . $signature);

        $response->assertStatus(200);
    }

    /**
     * Test that signature for one card does not work for another.
     */
    public function testSignatureNotTransferableToOtherCard(): void
    {
        $event = $this->createEventWithSecret();

        $params = ['card' => 'player1'];
        $signature = OrderTokenSignatureService::sign($event->order_token_secret, $params);

        // Try to use the same signature with a different card
        $response = $this->get('/order/' . $event->order_token . '?card=player2&signature=' . $signature);

        $response->assertStatus(403);
    }

    /**
     * Test that full order token includes the secret.
     */
    public function testFullOrderTokenFormat(): void
    {
        $event = $this->createEventWithSecret();

        $fullToken = $event->getFullOrderToken();

        $this->assertEquals(
            $event->order_token . '-' . $event->order_token_secret,
            $fullToken
        );
    }

    /**
     * Test that full order token for legacy event returns just the order token.
     */
    public function testFullOrderTokenLegacy(): void
    {
        $event = $this->createEventWithoutSecret();

        $fullToken = $event->getFullOrderToken();

        $this->assertEquals($event->order_token, $fullToken);
    }

    /**
     * Test public API rejects request with card token but no signature when event has secret.
     */
    public function testPublicApiRejectsUnsignedCardToken(): void
    {
        $event = $this->createEventWithSecret();

        $response = $this->withHeaders([
            'X-Event-Token' => $event->order_token,
            'X-Card-Token' => 'abcdef',
        ])->getJson('/api/v1/public/menu.json');

        $response->assertStatus(403);
    }

    /**
     * Test public API accepts request with card token and valid signature.
     */
    public function testPublicApiAcceptsSignedCardToken(): void
    {
        $event = $this->createEventWithSecret();
        $event->is_selling = true;
        $event->allow_unpaid_online_orders = true;
        $event->save();

        $params = ['card' => 'abcdef'];
        $signature = OrderTokenSignatureService::sign($event->order_token_secret, $params);

        $response = $this->withHeaders([
            'X-Event-Token' => $event->order_token,
            'X-Card-Token' => 'abcdef',
            'X-Signature' => $signature,
        ])->getJson('/api/v1/public/menu.json');

        // Should pass authentication (may get 423 if bar not open, but not 403)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test public API works without card token (no signature needed).
     */
    public function testPublicApiWorksWithoutCardToken(): void
    {
        $event = $this->createEventWithSecret();
        $event->is_selling = true;
        $event->allow_unpaid_online_orders = true;
        $event->save();

        $response = $this->withHeaders([
            'X-Event-Token' => $event->order_token,
        ])->getJson('/api/v1/public/menu.json');

        // Should pass authentication
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test public API with legacy event (no secret) allows unsigned card token.
     */
    public function testPublicApiLegacyEventAllowsUnsignedCardToken(): void
    {
        $event = $this->createEventWithoutSecret();
        $event->is_selling = true;
        $event->allow_unpaid_online_orders = true;
        $event->save();

        $response = $this->withHeaders([
            'X-Event-Token' => $event->order_token,
            'X-Card-Token' => 'abcdef',
        ])->getJson('/api/v1/public/menu.json');

        // Should pass authentication (legacy events don't require signatures)
        $this->assertNotEquals(403, $response->status());
    }
}
