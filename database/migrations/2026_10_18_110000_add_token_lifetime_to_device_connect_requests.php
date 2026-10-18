<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_connect_requests', function (Blueprint $table) {
            // Lifetime (in days) of the access token issued when this request is claimed.
            // Null falls back to the organisation's default.
            $table->unsignedSmallInteger('token_lifetime_days')->nullable()->after('pairing_code_accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_connect_requests', function (Blueprint $table) {
            $table->dropColumn('token_lifetime_days');
        });
    }
};
