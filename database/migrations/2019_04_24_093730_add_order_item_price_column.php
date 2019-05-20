<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrderItemPriceColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_items', function(Blueprint $table) {

            $table->double('price', 7, 2)->after('amount');

        });

        // update all existing order items.
        foreach (\App\Models\OrderItem::all() as $orderItem)
        {
            $orderItem->price = $orderItem->menuItem->price;
            $orderItem->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function(Blueprint $table) {

            $table->dropColumn('price');

        });
    }
}
