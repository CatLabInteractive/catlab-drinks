<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Laravel\Passport\ClientRepository;

class AddSwaggerOauthClient extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        app(ClientRepository::class)->create(
            null,
            'Swagger UI',
            url('docs/oauth2'),
            false,
            false
        );

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \Laravel\Passport\Client::where('name', '=', 'Swagger UI')->delete();
    }
}
