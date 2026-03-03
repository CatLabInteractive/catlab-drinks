<?php

namespace Tests\Unit;

use App\Models\Transaction;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Transaction model attributes and types.
 */
class TransactionModelTest extends TestCase
{
	/**
	 * Test that transaction types are correctly defined.
	 */
	public function testTransactionTypes(): void
	{
		$this->assertEquals('sale', Transaction::TYPE_SALE);
		$this->assertEquals('topup', Transaction::TYPE_TOPUP);
		$this->assertEquals('refund', Transaction::TYPE_REFUND);
		$this->assertEquals('unknown', Transaction::TYPE_UNKNOWN);
		$this->assertEquals('overflow', Transaction::TYPE_OVERFLOW);
		$this->assertEquals('reversal', Transaction::TYPE_REVERSAL);
		$this->assertEquals('reset', Transaction::TYPE_RESET);
	}

	/**
	 * Test overflow transaction ID.
	 */
	public function testOverflowId(): void
	{
		$this->assertEquals(-1, Transaction::ID_OVERFLOW);
	}

	/**
	 * Test that table name is correct.
	 */
	public function testTableName(): void
	{
		$transaction = new Transaction();
		$this->assertEquals('card_transactions', $transaction->getTable());
	}

	/**
	 * Test fillable attributes.
	 */
	public function testFillableAttributes(): void
	{
		$transaction = new Transaction();
		$fillable = $transaction->getFillable();

		$this->assertContains('transaction_type', $fillable);
		$this->assertContains('card_sync_id', $fillable);
		$this->assertContains('value', $fillable);
	}

	/**
	 * Test that merging from a non-unknown transaction with mismatching values throws.
	 */
	public function testMergeFromTransactionThrowsOnValueMismatch(): void
	{
		$this->expectException(\App\Exceptions\TransactionMergeException::class);

		$existing = new Transaction();
		$existing->transaction_type = Transaction::TYPE_SALE;
		$existing->value = 100;

		$incoming = new Transaction();
		$incoming->transaction_type = Transaction::TYPE_SALE;
		$incoming->value = 200;

		$existing->mergeFromTransaction($incoming);
	}

	/**
	 * Test that merging from a non-unknown transaction with matching values succeeds.
	 */
	public function testMergeFromTransactionSucceedsWithMatchingValues(): void
	{
		$existing = new Transaction();
		$existing->transaction_type = Transaction::TYPE_SALE;
		$existing->value = 100;

		$incoming = new Transaction();
		$incoming->transaction_type = Transaction::TYPE_SALE;
		$incoming->value = 100;

		$existing->mergeFromTransaction($incoming);

		// Should not throw
		$this->assertEquals(100, $existing->value);
	}

	/**
	 * Test that merging into an unknown transaction updates fields.
	 */
	public function testMergeIntoUnknownTransaction(): void
	{
		$existing = new Transaction();
		$existing->transaction_type = Transaction::TYPE_UNKNOWN;
		$existing->value = 0;

		$incoming = new Transaction();
		$incoming->transaction_type = Transaction::TYPE_SALE;
		$incoming->value = -500;
		$incoming->order_uid = 'order-123';

		$existing->mergeFromTransaction($incoming);

		$this->assertEquals(Transaction::TYPE_SALE, $existing->transaction_type);
		$this->assertEquals(-500, $existing->value);
		$this->assertEquals('order-123', $existing->order_uid);
	}
}
