<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHasSynctedToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_transactions', function (Blueprint $table) {

            $table->tinyInteger('has_synced')->default(0)->after('card_sync_id');

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

            $table->dropColumn('has_synced');

        });
    }
}
