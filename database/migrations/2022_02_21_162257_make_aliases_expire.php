<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeAliasesExpire extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_order_token_aliases', function (Blueprint $table) {

            $table->timestamp('expires_at')->after('alias')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('card_order_token_aliases', function (Blueprint $table) {

            $table->dropColumn('expires_at');

        });
    }
}
