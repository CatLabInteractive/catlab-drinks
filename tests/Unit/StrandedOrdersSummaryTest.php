<?php

namespace Tests\Unit;

use App\Models\StrandedOrdersSummary;
use PHPUnit\Framework\TestCase;

/**
 * Tests for StrandedOrdersSummary POPO model.
 */
class StrandedOrdersSummaryTest extends TestCase
{
	/**
	 * Test that count defaults to 0.
	 */
	public function testDefaultCountIsZero(): void
	{
		$summary = new StrandedOrdersSummary();
		$this->assertEquals(0, $summary->count);
	}

	/**
	 * Test that count can be set.
	 */
	public function testCountCanBeSet(): void
	{
		$summary = new StrandedOrdersSummary();
		$summary->count = 5;
		$this->assertEquals(5, $summary->count);
	}
}
