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
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;
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

		$items = $request->json('items');
		if (!is_array($items) || count($items) === 0) {
			$this->failSequence('Items must be a non-empty list.');
		}

		$ids = [];
		foreach ($items as $item) {
			$id = is_array($item) ? ($item['id'] ?? null) : null;
			if (!is_int($id)) {
				$this->failSequence('Every item must have an integer id.');
			}
			$ids[] = $id;
		}

		if (count(array_unique($ids)) !== count($ids)) {
			$this->failSequence('Every product can be listed only once.');
		}

		$valid = $event->menuItems()->whereIn('id', $ids)->count();
		if ($valid !== count($ids)) {
			$this->failSequence('Every id must be a product of this event that is not deleted.');
		}

		\DB::transaction(function () use ($event, $ids) {
			$position = 1;
			foreach ($ids as $id) {
				MenuItem::withTrashed()->where('id', $id)->update([ 'position' => $position++ ]);
			}

			$others = MenuItem::withTrashed()
				->where('event_id', $event->id)
				->whereNotIn('id', $ids)
				->orderBy('position')
				->orderBy('id')
				->pluck('id');

			foreach ($others as $id) {
				MenuItem::withTrashed()->where('id', $id)->update([ 'position' => $position++ ]);
			}
		});

		return \Response::json([ 'success' => true ]);
	}

	/**
	 * @param string $message
	 * @throws ResourceValidationException
	 */
	private function failSequence(string $message): void
	{
		$messages = new MessageCollection();
		$messages->add(new Message($message));

		throw ResourceValidationException::make($messages);
	}
}
