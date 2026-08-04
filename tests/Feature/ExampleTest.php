<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_getting_started_page()
    {
        $response = $this->get('/getting-started');

        $response
            ->assertStatus(200)
            ->assertSee('Getting Started')
            ->assertSee('Pairing a new POS device');
    }
}
