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

namespace App\Http\DeviceApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\MenuItemResourceDefinition;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Class MenuController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class MenuController extends \App\Http\Shared\V1\Controllers\MenuController
{
	const RESOURCE_DEFINITION = MenuItemResourceDefinition::class;

	public static function setRoutes(RouteCollection $routes, $only = [
		'index', 'view', 'edit'
	]): RouteCollection {
		return parent::setRoutes($routes, $only);
	}

	/**
	 * Checks if user is authorized to edit the entity.
	 * @param Request $request
	 * @param $entity
	 * @throws AuthorizationException
	 */
	protected function authorizeEdit(Request $request, $entity)
	{
		$this->authorize('editStatus', $entity);
	}
}
