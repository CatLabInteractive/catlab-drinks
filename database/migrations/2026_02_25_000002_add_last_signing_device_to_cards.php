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
		Schema::table('cards', function (Blueprint $table) {
			$table->unsignedBigInteger('last_signing_device_id')->nullable()->after('discount_percentage');
			$table->foreign('last_signing_device_id')->references('id')->on('devices')->nullOnDelete();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('cards', function (Blueprint $table) {
			$table->dropForeign(['last_signing_device_id']);
			$table->dropColumn('last_signing_device_id');
		});
	}
};
