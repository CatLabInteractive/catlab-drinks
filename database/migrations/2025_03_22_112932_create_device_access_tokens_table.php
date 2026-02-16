<?php

use App\Models\User;
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
        Schema::create('device_access_tokens', function (Blueprint $table) {

            $table->id();

			$table->foreignId('device_id')
				->constrained()
				->onDelete('cascade');

			$table->unsignedInteger('created_by')
				->constrained('users');

			$table->string('access_token');
			$table->timestamp('expires_at');

			$table->unique('access_token');

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
        Schema::dropIfExists('device_access_tokens');
    }
};
