<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
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
