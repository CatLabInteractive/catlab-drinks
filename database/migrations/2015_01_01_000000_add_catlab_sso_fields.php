<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCatlabSsoFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('username')->nullable()->after('id');
            $table->integer('catlab_id')->unsigned()->unique()->nullable()->after('username');
            $table->string('catlab_access_token')->nullable()->after('catlab_id');

            $table->string('password')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn('username');
            $table->dropColumn('catlab_id');
            $table->dropColumn('catlab_access_token');

            $table->string('password')->nullable(false)->change();

        });
    }
}
