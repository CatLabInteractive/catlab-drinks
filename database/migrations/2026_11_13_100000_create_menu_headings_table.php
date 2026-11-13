<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('menu_headings', function (Blueprint $table) {
			$table->increments('id');
			$table->integer('event_id')->unsigned();
			$table->string('name');
			$table->unsignedInteger('position')->default(0);
			$table->timestamps();
			$table->softDeletes();

			$table->foreign('event_id')->references('id')->on('events');
			// A single column index, so that the foreign key never depends on the
			// composite index alone (see the down() of add_position_to_menu_items).
			$table->index('event_id');
			$table->index(['event_id', 'position']);
		});

		Schema::table('menu_items', function (Blueprint $table) {
			$table->integer('heading_id')->unsigned()->nullable()->after('category_id');
			$table->foreign('heading_id')->references('id')->on('menu_headings');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('menu_items', function (Blueprint $table) {
			$table->dropForeign(['heading_id']);
			$table->dropColumn('heading_id');
		});

		Schema::dropIfExists('menu_headings');
	}
};
