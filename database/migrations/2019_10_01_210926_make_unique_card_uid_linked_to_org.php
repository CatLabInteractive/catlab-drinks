<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeUniqueCardUidLinkedToOrg extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {

            $table->dropUnique('cards_uid_unique');
            $table->unique([ 'uid', 'organisation_id' ]);

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
            $table->dropUnique('cards_uid_organisation_id_unique');
            $table->unique(['uid']);
        });
    }
}
