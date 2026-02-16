<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_connect_requests', function (Blueprint $table) {
            $table->id();

			$table->string('token')->unique();

			$table->integer('organisation_id')->unsigned();
			$table->foreign('organisation_id')
				->references('id')
				->on('organisations');
				
			$table->integer('created_by')->unsigned();
			$table->foreign('created_by')
				->references('id')
				->on('users');

			$table->bigInteger('device_id')->unsigned()->nullable();
			$table->foreign('device_id')
				->references('id')
				->on('devices');

			$table->char('device_uid', 36)->nullable();

			$table->string('pairing_code')->nullable();
			$table->boolean('pairing_code_accepted')->default(false);

			$table->timestamp('expires_at');
			$table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_connect_requests');
    }
};
