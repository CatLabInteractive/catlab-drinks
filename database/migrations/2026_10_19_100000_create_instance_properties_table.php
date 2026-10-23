<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small key/value store for instance-wide state that must survive deploys
 * on ephemeral filesystems (Heroku/Dokku), such as the anonymous instance
 * ID and the last usage report time.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('instance_properties', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('instance_properties');
    }
};
