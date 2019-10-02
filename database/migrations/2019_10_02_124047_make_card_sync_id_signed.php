<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeCardSyncIdSigned extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_transactions', function (Blueprint $table) {

            $table->integer('card_sync_id')->nullable()->change();

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

            $table->integer('card_sync_id')->unsigned()->nullable()->change();

        });
    }
}
