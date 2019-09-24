<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrganisationIdToCards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {

            $table->integer('organisation_id')->unsigned()->after('id');
            $table->foreign('organisation_id')->references('id')->on('cards');

            $table->index('updated_at');

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

            $table->dropForeign('cards_organisation_id_foreign');
            $table->dropColumn('organisation_id');

        });
    }
}
