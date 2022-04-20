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

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\ResourceDefinitions\AttendeeResourceDefinition;
use App\Models\Event;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class AttendeeController
 * @package App\Http\Api\V1\Controllers
 */
class AttendeeController extends Base\ResourceController
{
    const RESOURCE_DEFINITION = AttendeeResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'event';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            'events/{parentId}/attendees',
            'attendees',
            'AttendeeController',
            [
                'id' => self::RESOURCE_ID,
                'only' => [ 'index', 'view' ]
            ]
        );

        $childResource->tag('menu');

        $childResource->put('events/{parentId}/attendees/import', 'AttendeeController@import')
            ->parameters()->post('attendees');
    }

    public static function setPublicRoutes(RouteCollection $routes)
    {
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        return $event->attendees();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $eventId = $request->route('parentId');
        return Event::findOrFail($eventId);
    }


    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     * @return Model
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);
        return $entity;
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
