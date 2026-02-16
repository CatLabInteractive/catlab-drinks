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
        Schema::create('organisation_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('organisation_id');
            $table->string('gateway', 50);
            $table->text('credentials');
            $table->boolean('is_testing')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');

            $table->unique(['organisation_id', 'gateway']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('organisation_payment_gateways');
    }
};
