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

use App\Models\Device;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Class BasePolicy
 * @package App\Policies
 */
class BasePolicy
{
	/**
	 * @param User|null $user
	 * @return bool
	 */
	public function isAdmin(?Authorizable $user = null)
	{
		if ($user instanceof User) {
			return in_array($user->id, config('admin.admin_user_ids'));
		}

		return false;
	}

	/**
	 * @param User|null $user
	 * @param Event $event
	 * @return bool
	 */
	protected function isMyEvent(?Authorizable $user, Event $event, $allowDevices = false)
	{
		if (!$user) {
			return false;
		}

		if ($allowDevices) {
			return $this->isDeviceOrUserPartOfOrganisation($user, $event->organisation);
		}

		return $this->isMyOrganisation($user, $event->organisation);
	}

	/**
	 * @param User|Device|null $user
	 * @param Organisation $organisation
	 * @return mixed
	 */
	protected function isMyOrganisation(?Authorizable $user, Organisation $organisation)
	{
		if ($user instanceof User) {
			return $user->organisations->contains($organisation);
		} else {
			return false;
		}
	}

	/**
	 * @param User|Device|null $user
	 * @param Organisation $organisation
	 * @return mixed
	 */
	protected function isDeviceOrUserPartOfOrganisation(?Authorizable $user, Organisation $organisation)
	{
		if ($user instanceof User) {
			return $user->organisations->contains($organisation);
		} elseif ($user instanceof Device) {
			return $user->organisation_id == $organisation->id;
		} else {
			return false;
		}
	}
}
