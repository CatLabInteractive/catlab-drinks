<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links organisations to CatLab accounts "profiles" and adds the
 * incremental-sync bookkeeping columns for the ProfileMirror.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('organisations', function (Blueprint $table) {
            // No FK: the profile lives on the accounts server.
            // The UNIQUE key is the backstop for the mirror's link races.
            $table->unsignedBigInteger('profile_id')->nullable()->unique()->after('name');
            // Last fully-applied accounts sync_version; NULL forces a full sync.
            $table->unsignedBigInteger('profile_sync_version')->nullable()->after('profile_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // Throttle / failure-backoff marker for the ProfileMirror.
            $table->timestamp('last_profile_sync')->nullable()->after('catlab_access_token');
        });
    }

    public function down()
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['profile_id']);
            $table->dropColumn(['profile_id', 'profile_sync_version']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_profile_sync');
        });
    }
};
