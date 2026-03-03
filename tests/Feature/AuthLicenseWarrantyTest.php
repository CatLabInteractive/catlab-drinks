<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthLicenseWarrantyTest extends TestCase
{
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
