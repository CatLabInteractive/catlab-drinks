<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Order model status and payment_status constants.
 */
class OrderStatusConstantsTest extends TestCase
{
	// --- Fulfillment status constants ---

	/**
	 * Test that STATUS_PENDING constant exists and has correct value.
	 */
	public function testStatusPending(): void
	{
		$this->assertEquals('pending', Order::STATUS_PENDING);
	}

	/**
	 * Test that STATUS_PROCESSED constant exists and has correct value.
	 */
	public function testStatusProcessed(): void
	{
		$this->assertEquals('processed', Order::STATUS_PROCESSED);
	}

	/**
	 * Test that STATUS_DECLINED constant exists and has correct value.
	 */
	public function testStatusDeclined(): void
	{
		$this->assertEquals('declined', Order::STATUS_DECLINED);
	}

	/**
	 * Test that STATUS_PREPARED constant exists (new for table service).
	 */
	public function testStatusPrepared(): void
	{
		$this->assertEquals('prepared', Order::STATUS_PREPARED);
	}

	/**
	 * Test that STATUS_DELIVERED constant exists (new for table service).
	 */
	public function testStatusDelivered(): void
	{
		$this->assertEquals('delivered', Order::STATUS_DELIVERED);
	}

	// --- Payment status constants ---

	/**
	 * Test that PAYMENT_STATUS_UNPAID constant exists.
	 */
	public function testPaymentStatusUnpaid(): void
	{
		$this->assertEquals('unpaid', Order::PAYMENT_STATUS_UNPAID);
	}

	/**
	 * Test that PAYMENT_STATUS_PAID constant exists.
	 */
	public function testPaymentStatusPaid(): void
	{
		$this->assertEquals('paid', Order::PAYMENT_STATUS_PAID);
	}

	/**
	 * Test that PAYMENT_STATUS_VOIDED constant exists.
	 */
	public function testPaymentStatusVoided(): void
	{
		$this->assertEquals('voided', Order::PAYMENT_STATUS_VOIDED);
	}

	// --- Relationship existence checks ---

	/**
	 * Test that Order defines patron relationship (new for table service).
	 */
	public function testDefinesPatronRelationship(): void
	{
		$order = new Order();
		$this->assertTrue(
			method_exists($order, 'patron'),
			'Order model should define patron() relationship'
		);
	}

	/**
	 * Test that Order defines table relationship (new for table service).
	 */
	public function testDefinesTableRelationship(): void
	{
		$order = new Order();
		$this->assertTrue(
			method_exists($order, 'table'),
			'Order model should define table() relationship'
		);
	}

	/**
	 * Test that Order still defines existing relationships.
	 */
	public function testDefinesExistingRelationships(): void
	{
		$order = new Order();
		$this->assertTrue(method_exists($order, 'event'), 'Order should still have event() relationship');
		$this->assertTrue(method_exists($order, 'assignedDevice'), 'Order should still have assignedDevice() relationship');
		$this->assertTrue(method_exists($order, 'order'), 'Order should still have order() relationship');
		$this->assertTrue(method_exists($order, 'cardTransactions'), 'Order should still have cardTransactions() relationship');
	}
}
