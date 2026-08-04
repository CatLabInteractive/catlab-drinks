<?php

namespace Tests\Unit;

use App\Models\Patron;
use App\Models\Order;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Patron model attributes and methods.
 */
class PatronModelTest extends TestCase
{
	/**
	 * Test that the table name is correct.
	 */
	public function testTableName(): void
	{
		$patron = new Patron();
		$this->assertEquals('patrons', $patron->getTable());
	}

	/**
	 * Test that fillable attributes include name.
	 */
	public function testFillableAttributes(): void
	{
		$patron = new Patron();
		$fillable = $patron->getFillable();

		$this->assertContains('name', $fillable);
	}

	/**
	 * Test that event_id is NOT fillable (set via relationship).
	 */
	public function testEventIdNotFillable(): void
	{
		$patron = new Patron();
		$fillable = $patron->getFillable();

		$this->assertNotContains('event_id', $fillable);
	}

	/**
	 * Test that table_id is NOT fillable (set via relationship).
	 */
	public function testTableIdNotFillable(): void
	{
		$patron = new Patron();
		$fillable = $patron->getFillable();

		$this->assertNotContains('table_id', $fillable);
	}

	/**
	 * Test that name can be set directly.
	 */
	public function testNameAttribute(): void
	{
		$patron = new Patron();
		$patron->name = 'Alice';

		$this->assertEquals('Alice', $patron->name);
	}

	/**
	 * Test that name can be null (anonymous patrons).
	 */
	public function testNameCanBeNull(): void
	{
		$patron = new Patron();
		$patron->name = null;

		$this->assertNull($patron->name);
	}

	/**
	 * Test that the model defines the expected relationships.
	 */
	public function testDefinesEventRelationship(): void
	{
		$patron = new Patron();
		$this->assertTrue(
			method_exists($patron, 'event'),
			'Patron model should define event() relationship'
		);
	}

	/**
	 * Test that the model defines table relationship.
	 */
	public function testDefinesTableRelationship(): void
	{
		$patron = new Patron();
		$this->assertTrue(
			method_exists($patron, 'table'),
			'Patron model should define table() relationship'
		);
	}

	/**
	 * Test that the model defines orders relationship.
	 */
	public function testDefinesOrdersRelationship(): void
	{
		$patron = new Patron();
		$this->assertTrue(
			method_exists($patron, 'orders'),
			'Patron model should define orders() relationship'
		);
	}

	/**
	 * Test that getOutstandingBalance method exists.
	 */
	public function testHasGetOutstandingBalanceMethod(): void
	{
		$patron = new Patron();
		$this->assertTrue(
			method_exists($patron, 'getOutstandingBalance'),
			'Patron model should define getOutstandingBalance() method'
		);
	}

	/**
	 * Test that hasUnpaidOrders method exists.
	 */
	public function testHasHasUnpaidOrdersMethod(): void
	{
		$patron = new Patron();
		$this->assertTrue(
			method_exists($patron, 'hasUnpaidOrders'),
			'Patron model should define hasUnpaidOrders() method'
		);
	}

	/**
	 * Test that the model does NOT use SoftDeletes (patrons are not soft-deleted).
	 */
	public function testDoesNotUseSoftDeletes(): void
	{
		$patron = new Patron();
		$this->assertFalse(
			in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($patron)),
			'Patron model should NOT use SoftDeletes trait'
		);
	}
}
