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
use App\Models\Organisation;
use App\Models\Transaction;
use App\Models\User;

/**
 * Class TransactionPolicy
 * @package App\Policies
 */
class TransactionPolicy extends BasePolicy
{
    /**
     * @param User|null $user
     * @param Card $card
     * @return bool
     */
    public function index(?User $user, Card $card)
    {
        return $this->isMyCard($user, $card);
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return mixed
     */
    public function organisationIndex(?User $user, Organisation $organisation)
    {
        return $this->isMyOrganisation($user, $organisation);
    }

    /**
     * @param User|null $user
     * @param Card $card
     * @return bool
     */
    public function create(?User $user, Card $card)
    {
        return $this->isMyCard($user, $card);
    }

    /**
     * @param User|null $user
     * @param Transaction $transaction
     * @return bool
     */
    public function view(?User $user, Transaction $transaction)
    {
        return $this->isMyCard($user, $transaction->card);
    }

    /**
     * @param User|null $user
     * @param Transaction $transaction
     * @return bool
     */
    public function edit(?User $user, Transaction $transaction)
    {
        return $this->isMyCard($user, $transaction->card);
    }

    /**
     * @param User|null $user
     * @param Card $card
     * @return bool
     */
    public function destroy(?User $user, Card $card)
    {
        return false;
    }

    /**
     * @param Card $card
     * @return mixed
     */
    protected function isMyCard(?User $user, Card $card)
    {
        return $this->isMyOrganisation($user, $card->organisation);
    }
}
