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
		Schema::table('devices', function (Blueprint $table) {
			$table->unsignedInteger('category_filter_id')->nullable()->after('license_key');
			$table->foreign('category_filter_id')->references('id')->on('categories')->nullOnDelete();
		});

		Schema::table('orders', function (Blueprint $table) {
			$table->unsignedBigInteger('device_id')->nullable()->after('event_id');
			$table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('orders', function (Blueprint $table) {
			$table->dropForeign(['device_id']);
			$table->dropColumn('device_id');
		});

		Schema::table('devices', function (Blueprint $table) {
			$table->dropForeign(['category_filter_id']);
			$table->dropColumn('category_filter_id');
		});
	}
};
