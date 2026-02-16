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

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Class MenuItemPolicy
 * @package App\Policies
 */
class MenuItemPolicy extends BasePolicy
{
    /**
     * @param User|null $user
     * @param Event $event
     * @return bool
     */
    public function index(?Authorizable $user, Event $event)
    {
        return $this->isMyEvent($user, $event, true);
    }

    /**
     * @param User|null $user
     * @param Event $event
     * @return bool
     */
    public function create(?Authorizable $user, Event $event)
    {
        return $this->isMyEvent($user, $event);
    }

    /**
     * @param User|null $user
     * @param MenuItem $menuItem
     * @return bool
     */
    public function view(?Authorizable $user, MenuItem $menuItem)
    {
        return $this->isMyEvent($user, $menuItem->event, true);
    }

    /**
     * @param User|null $user
     * @param MenuItem $menuItem
     * @return bool
     */
    public function edit(?Authorizable $user, MenuItem $menuItem)
    {
        return $this->isMyEvent($user, $menuItem->event);
    }

	public function editStatus(?Authorizable $user, MenuItem $menuItem)
	{
		return $this->isMyEvent($user, $menuItem->event, true);
	}

    /**
     * @param User|null $user
     * @param MenuItem $menuItem
     * @return bool
     */
    public function destroy(?Authorizable $user, MenuItem $menuItem)
    {
        return $this->isMyEvent($user, $menuItem->event);
    }
}
