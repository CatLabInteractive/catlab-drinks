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

use Illuminate\Http\Request;

/**
 * Class ConnectController
 *
 * Handles the /connect endpoint used by QR codes to link POS devices.
 * The QR code encodes a URL like: https://drinks.catlab.eu/connect?data={BASE64}
 * where the BASE64 payload is JSON containing { api, token }.
 *
 * This page offers two options:
 * 1. Open the Android app (Play Store link)
 * 2. Continue in the browser (redirect to the POS on the customer's instance)
 *
 * @package App\Http\Controllers
 */
class ConnectController extends Controller
{
    /**
     * Show the connect page with options to open the app or continue in browser.
     */
    public function show(Request $request)
    {
        $dataParam = $request->query('data');

        if (!$dataParam) {
            return view('connect', [
                'error' => 'No connection data provided. Please scan a valid QR code.',
            ]);
        }

        // Decode and validate the connect data
        $json = base64_decode($dataParam, true);
        if ($json === false) {
            return view('connect', [
                'error' => 'Invalid connection data.',
            ]);
        }

        $connectData = json_decode($json, true);
        if (!$connectData || empty($connectData['api']) || empty($connectData['token'])) {
            return view('connect', [
                'error' => 'Invalid connection data: missing api or token.',
            ]);
        }

        $apiUrl = $connectData['api'];

        // Build the POS URL on the customer's instance, passing along the connect data
        $posUrl = rtrim($apiUrl, '/') . '/pos/?connect=' . urlencode($dataParam);

        return view('connect', [
            'connectData' => $connectData,
            'posUrl' => $posUrl,
        ]);
    }
}
