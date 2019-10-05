<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTopupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('topups', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('card_id')->unsigned();
            $table->foreign('card_id')->references('id')->on('cards');

            $table->string('type', 64);
            $table->string('status', 64);

            $table->string('uid', 36)->unique();

            $table->decimal('amount', 8, 2);

            $table->timestamps();
        });

        Schema::table('card_transactions', function(Blueprint $table) {

            $table->integer('topup_id')->unsigned()->after('order_uid')->nullable();
            $table->foreign('topup_id')->references('id')->on('topups');

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
            $table->dropForeign('card_transactions_topup_id_foreign');
            $table->dropColumn('topup_id');
        });

        Schema::dropIfExists('topups');
    }
}
