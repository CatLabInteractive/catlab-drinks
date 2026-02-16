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
			$table->char('order_uid', 42)->nullable()->change();
			$table->char('topup_uid', 42)->nullable()->change();
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
			$table->char('order_uid', 36)->nullable()->change();
			$table->char('topup_uid', 36)->nullable()->change();
		});
    }
};
