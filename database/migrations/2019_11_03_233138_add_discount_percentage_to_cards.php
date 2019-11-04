<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDiscountPercentageToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->tinyInteger('discount_percentage')->default(0)->after('transaction_count');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('discount_percentage')->default(0)->after('status');
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
            $table->dropColumn('discount_percentage');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
        });
    }
}
