<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class AddOrderTokenSecretToEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('order_token_secret', 32)->nullable()->after('order_token');
        });

        // Generate secrets for existing events
        foreach (\App\Models\Event::withTrashed()->whereNull('order_token_secret')->get() as $event) {
            $event->order_token_secret = Str::random(16);
            $event->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('order_token_secret');
        });
    }
}
