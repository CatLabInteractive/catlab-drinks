<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
		Schema::table('menu_items', function (Blueprint $table) {
			$table->unsignedInteger('position')->default(0)->after('category_id');
			$table->index(['event_id', 'position']);
		});

		// Number the existing rows per event in id order (soft deleted rows included).
		$counters = [];
		DB::table('menu_items')->orderBy('id')->select(['id', 'event_id'])->each(function ($row) use (&$counters) {
			$counters[$row->event_id] = ($counters[$row->event_id] ?? 0) + 1;
			DB::table('menu_items')->where('id', $row->id)->update(['position' => $counters[$row->event_id]]);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		// MySQL dropped the implicit index of the event_id foreign key when the
		// composite index took over that role. Restore it first, otherwise the
		// composite index cannot be dropped (error 1553). After an earlier
		// rollback the index is explicit and still there.
		if (!Schema::hasIndex('menu_items', 'menu_items_event_id_foreign')) {
			Schema::table('menu_items', function (Blueprint $table) {
				$table->index('event_id', 'menu_items_event_id_foreign');
			});
		}

		Schema::table('menu_items', function (Blueprint $table) {
			$table->dropIndex(['event_id', 'position']);
			$table->dropColumn('position');
		});
	}
};
