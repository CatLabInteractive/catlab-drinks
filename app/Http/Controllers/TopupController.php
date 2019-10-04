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
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class TopupController
 * @package App\Http\Api\V1\Controllers
 */
class TopupController extends Controller
{
    /**
     * @param $cardUid
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function topup($cardUid)
    {
        $cards = Card::where('uid', '=', $cardUid);
        if ($cards->count() === 0) {
            throw new ModelNotFoundException('Card not found.');
        }

        if ($cards->count() > 1) {
            throw new ModelNotFoundException('Duplicate of card found. Topup is not supported.');
        }

        /** @var Card $card */
        $card = $cards->first();

        return view('topup/topup');
    }
}
