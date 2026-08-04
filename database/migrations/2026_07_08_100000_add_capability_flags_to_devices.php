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
			$table->boolean('allow_sales')->default(true)->after('allow_live_orders');
			$table->boolean('allow_topup')->default(true)->after('allow_sales');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('devices', function (Blueprint $table) {
			$table->dropColumn('allow_sales');
			$table->dropColumn('allow_topup');
		});
	}
};
