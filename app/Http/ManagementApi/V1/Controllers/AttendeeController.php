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

use App\Http\ManagementApi\V1\ResourceDefinitions\AttendeeResourceDefinition;
use App\Models\Event;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Http\Request;

/**
 * Class AttendeeController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class AttendeeController extends \App\Http\Shared\V1\Controllers\AttendeeController
{
    const RESOURCE_DEFINITION = AttendeeResourceDefinition::class;

    /**
     * @param RouteCollection $routes
     * @param string[] $only
     * @return RouteCollection
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes, array $only = [
        'index', 'view', 'store', 'edit', 'destroy'
    ]): RouteCollection
    {
        $childResource = parent::setRoutes($routes, $only);

        $childResource->put('events/{parentId}/attendees/import', 'AttendeeController@import')
            ->parameters()->post('attendees');

        return $childResource;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        $event->attendees()->delete();

        $attendees = $request->json('attendees');
        $lines = explode("\n", $attendees);

        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) > 1) {
                $alias = trim(array_shift($parts));
                $nameInput = implode(':', $parts);

                // Do we have tabs?
                $nameParts = explode("\t", $nameInput);

                $name = trim($nameParts[0]);
                if (!$alias || !$name) {
                    continue;
                }

                $attributes = [
                    'alias' => $alias,
                    'name' => $name
                ];

                if (isset($nameParts[1])) {
                    $attributes['email'] = trim($nameParts[1]);
                }

                $event->attendees()->create($attributes);
            }
        }

        return \Response::json([ 'success' => true ]);
    }
}
