<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthLicenseWarrantyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        config(['instance.registration_open' => true]);
    }

    public function testLoginFormShowsLicenseWarrantyWarning()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(__('welcome.license_warranty'));
    }

    public function testRegisterFormShowsLicenseWarrantyWarning()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee(__('welcome.license_warranty'));
    }
}
