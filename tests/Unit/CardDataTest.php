<?php

namespace Tests\Unit;

use App\Models\CardData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CardData model used in NFC card payload.
 */
class CardDataTest extends TestCase
{
	/**
	 * Test that CardData can store balance.
	 */
	public function testCardDataBalance(): void
	{
		$cardData = new CardData();
		$cardData->balance = 5000;
		$this->assertEquals(5000, $cardData->balance);
	}

	/**
	 * Test that CardData can store transaction count.
	 */
	public function testCardDataTransactionCount(): void
	{
		$cardData = new CardData();
		$cardData->transactionCount = 42;
		$this->assertEquals(42, $cardData->transactionCount);
	}

	/**
	 * Test that CardData can store previous transactions.
	 */
	public function testCardDataPreviousTransactions(): void
	{
		$cardData = new CardData();
		$cardData->previousTransactions = [100, -200, 300, -150, 500];
		$this->assertCount(5, $cardData->previousTransactions);
		$this->assertEquals(100, $cardData->previousTransactions[0]);
	}

	/**
	 * Test that CardData can store discount percentage.
	 */
	public function testCardDataDiscountPercentage(): void
	{
		$cardData = new CardData();
		$cardData->discount_percentage = 25;
		$this->assertEquals(25, $cardData->discount_percentage);
	}

	/**
	 * Test that CardData properties are uninitialized by default.
	 */
	public function testCardDataDefaults(): void
	{
		$cardData = new CardData();
		$this->assertNull($cardData->transactionCount);
		$this->assertNull($cardData->balance);
		$this->assertNull($cardData->previousTransactions);
	}
}
