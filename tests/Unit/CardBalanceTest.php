<?php

namespace Tests\Unit;

use App\Models\Card;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Card model balance and transaction validation.
 */
class CardBalanceTest extends TestCase
{
	/**
	 * Test that canAfford returns true when balance is sufficient.
	 */
	public function testCanAffordWithSufficientBalance(): void
	{
		$card = $this->getMockBuilder(Card::class)
			->onlyMethods(['getBalance'])
			->getMock();

		$card->method('getBalance')->willReturn(1000);

		$order = $this->createMock(\App\Models\Order::class);
		$order->method('getDiscountedCurrentCardCost')->willReturn(500);

		$this->assertTrue($card->canAfford([$order]));
	}

	/**
	 * Test that canAfford returns false when balance is insufficient.
	 */
	public function testCanAffordWithInsufficientBalance(): void
	{
		$card = $this->getMockBuilder(Card::class)
			->onlyMethods(['getBalance'])
			->getMock();

		$card->method('getBalance')->willReturn(100);

		$order = $this->createMock(\App\Models\Order::class);
		$order->method('getDiscountedCurrentCardCost')->willReturn(500);

		$this->assertFalse($card->canAfford([$order]));
	}

	/**
	 * Test that canAfford returns true for zero-cost orders.
	 */
	public function testCanAffordWithZeroCost(): void
	{
		$card = $this->getMockBuilder(Card::class)
			->onlyMethods(['getBalance'])
			->getMock();

		$card->method('getBalance')->willReturn(0);

		$order = $this->createMock(\App\Models\Order::class);
		$order->method('getDiscountedCurrentCardCost')->willReturn(0);

		$this->assertTrue($card->canAfford([$order]));
	}

	/**
	 * Test that spend throws InsufficientFundsException when balance is too low.
	 */
	public function testSpendThrowsWhenInsufficient(): void
	{
		$this->expectException(\App\Exceptions\InsufficientFundsException::class);

		$card = $this->getMockBuilder(Card::class)
			->onlyMethods(['getBalance'])
			->getMock();

		$card->method('getBalance')->willReturn(100);

		$order = $this->createMock(\App\Models\Order::class);
		$order->method('getDiscountedCurrentCardCost')->willReturn(500);

		$card->spend($order);
	}

	/**
	 * Test that the lastSigningDevice relationship exists.
	 */
	public function testLastSigningDeviceRelationshipExists(): void
	{
		$card = new Card();

		$this->assertTrue(
			method_exists($card, 'lastSigningDevice'),
			'Card should have lastSigningDevice relationship'
		);
	}
}
