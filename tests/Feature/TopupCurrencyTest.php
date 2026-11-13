<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Tests\Feature;

use App\Http\Controllers\TopupController;
use App\Models\Card;
use App\Models\Organisation;
use App\Models\OrganisationPaymentGateway;
use App\Models\Topup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Card top-ups are labelled with, and paid in, the organisation's currency.
 */
class TopupCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function testPurchaseParametersUseOrganisationCurrency(): void
    {
        $organisation = new Organisation(['name' => 'Test org']);
        $organisation->currency = 'USD';
        $card = new Card();
        $card->uid = 'abc123';
        $card->setRelation('organisation', $organisation);
        $topup = new Topup(['amount' => 12.5]);
        $topup->uid = 'topup-uid';

        $method = new \ReflectionMethod(TopupController::class, 'getParameters');
        $params = $method->invoke(new TopupController(), $card, $topup);

        $this->assertSame('USD', $params['currency']);
        $this->assertSame('12.50', $params['amount']);
    }

    public function testTopupFormShowsAmountsInOrganisationCurrency(): void
    {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $organisation = Organisation::factory()->create(['currency' => 'USD']);

        $gateway = new OrganisationPaymentGateway([
            'gateway' => OrganisationPaymentGateway::GATEWAY_PAYNL,
            'is_testing' => true,
            'is_active' => true,
        ]);
        $gateway->organisation_id = $organisation->id;
        $gateway->credentials = ['apiToken' => 'token', 'apiSecret' => 'secret', 'serviceId' => 'SL-1234-5678'];
        $gateway->save();

        $card = new Card();
        $card->organisation_id = $organisation->id;
        $card->uid = 'cardabc';
        $card->order_token = 'ordertoken123';
        $card->transaction_count = 0;
        $card->save();

        $response = $this->get('/topup/cardabc');
        $response->assertStatus(200);
        $response->assertSee('$0.00');
        $response->assertSee('$10.00');
        $response->assertDontSee('€');
    }
}
