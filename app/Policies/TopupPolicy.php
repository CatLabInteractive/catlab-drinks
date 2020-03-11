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

namespace App\Policies;

use App\Models\Card;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Topup;
use App\Models\User;

/**
 * Class TopupPolicy
 * @package App\Policies
 */
class TopupPolicy extends BasePolicy
{
    /**
     * @param User|null $user
     * @param Card $card
     * @return bool
     */
    public function index(?User $user, Card $card)
    {
        return $this->isMyEvent($user, $card->event);
    }

    /**
     * @param User|null $user
     * @param Card $card
     * @return bool
     */
    public function create(?User $user, Card $card)
    {
        return $this->isMyEvent($user, $card->event);
    }

    /**
     * @param User|null $user
     * @param Topup $topup
     * @return bool
     */
    public function view(?User $user, Topup $topup)
    {
        return $this->isMyEvent($user, $topup->card->event);
    }

    /**
     * @param User|null $user
     * @param Topup $topup
     * @return bool
     */
    public function edit(?User $user, Topup $topup)
    {
        return $this->isMyEvent($user, $topup->card->event);
    }

    /**
     * @param User|null $user
     * @param Topup $topup
     * @return bool
     */
    public function destroy(?User $user, Topup $topup)
    {
        return $this->isMyEvent($user, $topup->card->event);
    }
}
