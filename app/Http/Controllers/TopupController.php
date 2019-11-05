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

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Topup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Neuron\Config;
use Neuron\Net\Response;
use Omnipay\Omnipay;
use Omnipay\Paynl\Gateway;
use Omnipay\Paynl\Message\Request\CompletePurchaseRequest;
use Omnipay\Paynl\Message\Response\CompletePurchaseResponse;
use Ramsey\Uuid\Uuid;

/**
 * Class TopupController
 * @package App\Http\Api\V1\Controllers
 */
class TopupController extends Controller
{
    private $minTopup;
    private $maxTopup;

    public function __construct()
    {
        $this->minTopup = config('payment.minTopup', 10);
        $this->maxTopup = config('payment.maxTopup', 250);
    }

    /**
     * @param $cardUid
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function topupForm($cardUid)
    {
        $gateway = $this->createPayNLGateway();
        if (!$gateway) {
            return view('topup.notAvailable');
        }

        $card = $this->getCard($cardUid);
        return view(
            'topup.topupForm', [
                'minTopup' => number_format($this->minTopup, 2),
                'maxTopup' => number_format($this->maxTopup, 2),
                'action' => action('\App\Http\Controllers\TopupController@processTopup', [ $cardUid ])
            ]
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $cardUid
     * @throws \Exception
     */
    public function processTopup(\Illuminate\Http\Request $request, $cardUid)
    {
        $card = $this->getCard($cardUid);

        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:' . $this->minTopup . '|max:' . $this->maxTopup
        ]);

        $amount = $validatedData['amount'];

        // create the topup.
        $topup = new Topup();
        $topup->uid = Uuid::uuid1();
        $topup->type = Topup::TYPE_ONLINE;
        $topup->card()->associate($card);
        $topup->status = Topup::STATUS_PENDING;
        $topup->amount = $amount;
        $topup->save();

        $card = $this->getCard($cardUid);

        // build the requets
        $omnipayRequest = $this->getParameters($card, $topup);

        $gateway = $this->createPayNLGateway();
        if (!$gateway) {
            abort(500, 'No payment gateway set.');
        }

        $response = $gateway->purchase($omnipayRequest)->send();

        if ($response->isRedirect()) {
            // redirect to offsite payment gateway
            $response->redirect();
        } elseif ($response->isSuccessful()) {
            // payment was successful: update database
            print_r($response);
        } else {
            // payment failed: display message to customer
            echo $response->getMessage();
        }
    }

    /**
     * @param $cardUid
     * @param $topupId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function status($cardUid, $topupId)
    {
        $card = $this->getCard($cardUid);

        /** @var Topup $topup */
        $topup = Topup::findOrFail($topupId);

        if ($topup->card->id !== $card->id) {
            abort(400, 'Card doesn\'t match topup card. But hey, nice try.');
            return;
        }

        $gateway = $this->createPayNLGateway();

        $response = $gateway->completePurchase($this->getParameters($card, $topup))->send();

        $topup->logs()->create([
            'message' => json_encode($response->getData())
        ]);

        //dd($response->isSuccessful() && $response->isPaid());
        $successful = $response->isSuccessful();
        if ($response instanceof CompletePurchaseResponse) {
            $successful = $successful && $response->isPaid();
        }

        if ($successful) {
            $topup->success($response->getData());
        } elseif ($response->isCancelled()) {
            $topup->cancel($response->getData());
        }

        return view('topup/status', [
            'topup' =>  $topup,
            'retryUrl' => action('TopupController@topupForm', [ $cardUid ])
        ]);
    }

    /**
     * @param Card $card
     * @param Topup $topup
     * @return array
     */
    protected function getParameters(Card $card, Topup $topup) {
        return [
            'token' => $topup->id,
            'currency' => 'EUR',
            'description' => 'Topup card ' . $card->uid,
            'amount' => number_format((float)$topup->amount, 2, '.', ''),
            'finishUrl' => action('TopupController@status', [ $card->uid, $topup->id ]),
            'returnUrl' => action('TopupController@status', [ $card->uid, $topup->id ]),
            'cancelUrl' => action('TopupController@status', [ $card->uid, $topup->id ]),
            'notifyUrl' => action('TopupController@status', [ $card->uid, $topup->id ])
        ];
    }

    /**
     * @param $cardUid
     * @return Card
     */
    protected function getCard($cardUid)
    {
        $cards = Card::where('uid', '=', $cardUid);
        if ($cards->count() === 0) {
            throw new ModelNotFoundException('Card not found.');
        }

        if ($cards->count() > 1) {
            throw new ModelNotFoundException('Duplicate of card found. Topup is not supported.');
        }

        return $cards->first();
    }

    /**
     * @return \Omnipay\Paynl\Gateway
     */
    protected function createPayNLGateway()
    {
        if (!config('omnipay.paynl.apiToken')) {
            return false;
        }

        /** @var Gateway $gateway */
        $gateway = Omnipay::create('Paynl');

        $gateway->setTokenCode(config('omnipay.paynl.apiToken'));
        $gateway->setApitoken(config('omnipay.paynl.apiSecret'));
        $gateway->setServiceId(config('omnipay.paynl.serviceId'));
        $gateway->setParameter('clientIp', $_SERVER['REMOTE_ADDR']);
        $gateway->setTestMode(config('omnipay.paynl.testing'));

        /*
        if ($profileId = $this->request->input('profile')) {
            $gateway->setParameter('paymentMethod', $profileId);
        }
        */

        return $gateway;
    }
}
