<?php

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrganisationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name');

            $table->boolean('payment_cash')->default(0);
            $table->boolean('payment_vouchers')->default(0);
            $table->double('payment_voucher_value', 7, 2)->nullable()->default(null);
            $table->boolean('payment_cards')->default(0);

            $table->timestamps();
        });

        Schema::create('organisation_user', function (Blueprint $table) {

            $table->increments('id');

            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');

            $table->integer('organisation_id')->unsigned();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->unique([ 'user_id', 'organisation_id' ]);

            $table->timestamps();

        });

        foreach (\App\Models\User::all() as $user) {
            /** @var User $user */
            $organisation = new Organisation([
                'name' => $user->username
            ]);

            $organisation->save();
            $user->organisations()->attach($organisation);
        }

        Schema::table('events', function(Blueprint $table) {

            $table->integer('organisation_id')->unsigned()->after('user_id')->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations');

        });

        Schema::table('events', function (Blueprint $table) {

            $table->dropForeign('events_user_id_foreign');
            $table->dropColumn('user_id');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function(Blueprint $table) {

            $table->integer('user_id')->unsigned()->after('waiter_token')->nullable();

        });

        foreach (\App\Models\Event::all() as $event) {

            /** @var \App\Models\Event $event */
            $event->user_id = $event->organisation->users()->first()->id;
            $event->save();

        }

        Schema::table('events', function(Blueprint $table) {

            $table->foreign('user_id')->references('id')->on('users');
            $table->dropForeign('events_organisation_id_foreign');
            $table->dropColumn('organisation_id');

        });

        Schema::dropIfExists('organisation_user');
        Schema::dropIfExists('organisations');
    }
}
