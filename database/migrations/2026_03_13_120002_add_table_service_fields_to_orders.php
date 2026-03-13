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
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('patron_id')->unsigned()->nullable()->after('event_id');
            $table->foreign('patron_id')->references('id')->on('patrons');

            $table->integer('table_id')->unsigned()->nullable()->after('patron_id');
            $table->foreign('table_id')->references('id')->on('tables');

            $table->string('payment_status', 32)->default('paid')->after('paid');
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
            $table->dropForeign(['patron_id']);
            $table->dropColumn('patron_id');

            $table->dropForeign(['table_id']);
            $table->dropColumn('table_id');

            $table->dropColumn('payment_status');
        });
    }
};
