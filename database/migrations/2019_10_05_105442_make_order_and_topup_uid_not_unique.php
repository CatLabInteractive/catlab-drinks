<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeOrderAndTopupUidNotUnique extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_transactions', function(Blueprint $table) {

            $table->dropUnique('card_transactions_order_uid_unique');
            $table->dropUnique('card_transactions_topup_uid_unique');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('card_transactions', function(Blueprint $table) {

            $table->unique('order_uid');
            $table->unique('topup_uid');

        });
    }
}
