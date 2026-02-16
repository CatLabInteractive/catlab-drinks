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

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Class OrganisationPolicy
 * @package App\Policies
 */
class OrganisationPolicy extends BasePolicy
{
    /**
     * @param User|null $user
     * @return bool
     */
    public function index(?Authorizable $user)
    {
        return true;
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return bool
     */
    public function create(?Authorizable $user, Organisation $organisation)
    {
        return $this->isMyOrganisation($user, $organisation);
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return bool
     */
    public function view(?Authorizable $user, Organisation $organisation)
    {
        return $this->isDeviceOrUserPartOfOrganisation($user, $organisation);
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return bool
     */
    public function edit(?Authorizable $user, Organisation $organisation)
    {
        return $this->isMyOrganisation($user, $organisation);
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return bool
     */
    public function destroy(?Authorizable $user, Organisation $organisation)
    {
        return $this->isMyOrganisation($user, $organisation);
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return mixed
     */
    public function mergeTransactions(?Authorizable $user, Organisation $organisation) {
        return $this->isDeviceOrUserPartOfOrganisation($user, $organisation);
    }

    /**
     * @param User|null $user
     * @param Organisation $organisation
     * @return mixed
     */
    public function financialOverview(?Authorizable $user, Organisation $organisation) {
        return $this->isMyOrganisation($user, $organisation);
    }
}
