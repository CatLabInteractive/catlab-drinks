<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MovePaymentOptionsToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('payment_cash');
            $table->dropColumn('payment_vouchers');
            $table->dropColumn('payment_voucher_value');
            $table->dropColumn('payment_cards');

            $table->string('secret')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('payment_cash')->default(0);
            $table->boolean('payment_vouchers')->default(0);
            $table->double('payment_voucher_value', 7, 2)->nullable()->default(null);
            $table->boolean('payment_cards')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('payment_cash');
            $table->dropColumn('payment_vouchers');
            $table->dropColumn('payment_voucher_value');
            $table->dropColumn('payment_cards');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->boolean('payment_cash')->default(0);
            $table->boolean('payment_vouchers')->default(0);
            $table->double('payment_voucher_value', 7, 2)->nullable()->default(null);
            $table->boolean('payment_cards')->default(0);

            $table->dropColumn('secret');
        });
    }
}
