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
		Schema::table('card_transactions', function (Blueprint $table) {
			$table->unsignedBigInteger('uploaded_by_device_id')->nullable();
			$table->foreign('uploaded_by_device_id')->references('id')->on('devices')->nullOnDelete();
			$table->boolean('unauthorized')->default(false);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('card_transactions', function (Blueprint $table) {
			$table->dropForeign(['uploaded_by_device_id']);
			$table->dropColumn('uploaded_by_device_id');
			$table->dropColumn('unauthorized');
		});
	}
};
