<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeCardAliasUniqueToOrganisation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_order_token_aliases', function (Blueprint $table) {
            $table->dropUnique('card_order_token_aliases_alias_unique');
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
            $table->unique('alias');
        });
    }
}
