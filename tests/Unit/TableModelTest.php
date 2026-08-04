<?php

namespace Tests\Unit;

use App\Models\Table;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Table model attributes.
 */
class TableModelTest extends TestCase
{
	/**
	 * Test that the table name is correct.
	 */
	public function testTableName(): void
	{
		$table = new Table();
		$this->assertEquals('tables', $table->getTable());
	}

	/**
	 * Test that fillable attributes include table_number and name.
	 */
	public function testFillableAttributes(): void
	{
		$table = new Table();
		$fillable = $table->getFillable();

		$this->assertContains('table_number', $fillable);
		$this->assertContains('name', $fillable);
	}

	/**
	 * Test that fillable does NOT include event_id (set via relationship).
	 */
	public function testEventIdNotFillable(): void
	{
		$table = new Table();
		$fillable = $table->getFillable();

		$this->assertNotContains('event_id', $fillable);
	}

	/**
	 * Test that table_number can be set directly.
	 */
	public function testTableNumberAttribute(): void
	{
		$table = new Table();
		$table->table_number = 5;

		$this->assertEquals(5, $table->table_number);
	}

	/**
	 * Test that name can be set directly.
	 */
	public function testNameAttribute(): void
	{
		$table = new Table();
		$table->name = 'VIP Table';

		$this->assertEquals('VIP Table', $table->name);
	}

	/**
	 * Test that the model uses SoftDeletes trait.
	 */
	public function testUsesSoftDeletes(): void
	{
		$table = new Table();
		$this->assertTrue(
			method_exists($table, 'trashed'),
			'Table model should use SoftDeletes trait'
		);
	}

	/**
	 * Test that the model defines the expected relationships.
	 */
	public function testDefinesEventRelationship(): void
	{
		$table = new Table();
		$this->assertTrue(
			method_exists($table, 'event'),
			'Table model should define event() relationship'
		);
	}

	/**
	 * Test that the model defines patrons relationship.
	 */
	public function testDefinesPatronsRelationship(): void
	{
		$table = new Table();
		$this->assertTrue(
			method_exists($table, 'patrons'),
			'Table model should define patrons() relationship'
		);
	}

	/**
	 * Test that the model defines orders relationship.
	 */
	public function testDefinesOrdersRelationship(): void
	{
		$table = new Table();
		$this->assertTrue(
			method_exists($table, 'orders'),
			'Table model should define orders() relationship'
		);
	}

	/**
	 * Test that getLatestPatron method exists.
	 */
	public function testHasGetLatestPatronMethod(): void
	{
		$table = new Table();
		$this->assertTrue(
			method_exists($table, 'getLatestPatron'),
			'Table model should define getLatestPatron() method'
		);
	}

	/**
	 * Test that bulkGenerate static method exists.
	 */
	public function testHasBulkGenerateStaticMethod(): void
	{
		$this->assertTrue(
			method_exists(Table::class, 'bulkGenerate'),
			'Table model should define static bulkGenerate() method'
		);
	}
}
