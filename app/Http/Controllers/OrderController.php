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

use App\Models\Event;
use App\Services\OrderTokenSignatureService;
use Illuminate\Http\Request;

/**
 * Class OrderController
 * @package App\Http\Controllers
 */
class OrderController
{
    /**
     * @param Request $request
     * @param $orderToken
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function view(Request $request, $orderToken)
    {
        $event = Event::getFromOrderToken($orderToken);
        if (!$event) {
            abort(404, 'Event not found.');
            return;
        }

        // Check if query parameters need signature validation
        $queryParams = $request->only(OrderTokenSignatureService::SIGNABLE_PARAMS);
        $secret = $event->getOrderTokenSecret();

        if ($secret && OrderTokenSignatureService::hasSignableParams($queryParams)) {
            $signature = $request->query('signature');
            if (!$signature || !OrderTokenSignatureService::verify($secret, $queryParams, $signature)) {
                abort(403, 'Invalid signature.');
                return;
            }
        }

        $baseUrl = '/order/' . $event->order_token . '/';
        $token = $event->order_token;

        return view(
            'order.index',
            [
                'baseUrl' => $baseUrl,
                'token' => $token,
                'signature' => $request->query('signature', ''),
                'cardToken' => $request->query('card', ''),
                'orderName' => $request->query('name', ''),
            ]
        );
    }
}