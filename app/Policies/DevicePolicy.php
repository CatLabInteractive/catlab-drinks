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
 * Class DevicePolicy
 * @package App\Policies
 */
class DevicePolicy extends BasePolicy
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
	 * @param $user
	 * @return bool
	 */
	public function create(?Authorizable $user)
	{
		return false;
	}

	public function connectDevice(?Authorizable $user, Organisation $organisation)
	{
		return $this->isMyOrganisation($user, $organisation);
	}

	public function view(?Authorizable $user, Device $device)
	{
		// A device can only view itself
		if ($user instanceof Device) {
			return $user->id === $device->id;
		}

		return $this->isMyOrganisation($user, $device->organisation);
	}

	/**
	 * Only a device can view its secret.
	 * @param null|Authorizable $user 
	 * @param Device $device 
	 * @return bool 
	 */
	public function viewSecret(?Authorizable $user, Device $device)
	{
		if ($user instanceof Device) {
			return $user->id === $device->id;
		}

		return false;
	}

	public function edit(?Authorizable $user, Device $device)
	{
		return $this->isMyOrganisation($user, $device->organisation);
	}

	public function destroy(?Authorizable $user, Device $device)
	{
		return $this->isMyOrganisation($user, $device->organisation);
	}
}
