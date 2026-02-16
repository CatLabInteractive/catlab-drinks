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
		Schema::create('devices', function (Blueprint $table) {

			$table->id();
			$table->char('uid', 36);

			$table->integer('organisation_id')->unsigned();
			$table->foreign('organisation_id')->references('id')->on('organisations');

			$table->unique([ 'uid', 'organisation_id' ]);

			$table->string('name');
			$table->string('description')->nullable();

			$table->text('public_key')->nullable();
			$table->text('license_key')->nullable();

			$table->string('secret_key');
			
			$table->timestamp('approved_at')->nullable();
			$table->integer('approved_by')->unsigned()->nullable();
			$table->foreign('approved_by')->references('id')->on('users');

			$table->timestamp('last_ping')->nullable();
			$table->timestamp('last_activity')->nullable();
			
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
		Schema::dropIfExists('devices');
	}
};
