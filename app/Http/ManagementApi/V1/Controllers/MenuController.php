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

namespace App\Http\ManagementApi\V1\Controllers;

use App\Models\Event;
use App\Models\MenuItem;
use App\Services\SequenceWriter;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Http\Request;

/**
 * Class MenuController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class MenuController extends \App\Http\Shared\V1\Controllers\MenuController
{
	public static function setRoutes(RouteCollection $routes, $only = [
		'index', 'view', 'store', 'edit', 'destroy'
	]): RouteCollection {
		$childResource = parent::setRoutes($routes, $only);

		$childResource->put('events/{parentId}/menu/sequence', 'MenuController@sequence')
			->summary('Store the order in which the products of an event are shown')
			->parameters()->post('items');

		return $childResource;
	}

	/**
	 * Store the menu sequence. Listed products get position 1..k in list order,
	 * the other products of the event follow in their current order.
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function sequence(Request $request)
	{
		/** @var Event $event */
		$event = $this->getParent($request);
		$this->authorize('create', [ MenuItem::class, $event ]);

		app(SequenceWriter::class)->write($event->menuItems(), $request->json('items'));

		return \Response::json([ 'success' => true ]);
	}
}
