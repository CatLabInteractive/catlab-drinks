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

namespace App\Http\Shared\V1\Controllers;

use App\Factories\OrderEntityFactory;
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Http\Shared\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Event;
use Auth;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition as ResourceDefinitionContract;
use CatLab\Charon\Models\RESTResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class OrderController
 * @package App\Http\Shared\V1\Controllers
 */
abstract class OrderController extends ResourceController
{
    const RESOURCE_DEFINITION = OrderResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'event';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     * @param string[] $only
     * @return RouteCollection
     * @throws InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes, array $only = [
        'index', 'view'
    ]) {
        $parentPath = 'events/{' . self::PARENT_RESOURCE_ID . '}/orders';

        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            $parentPath,
            'orders',
            'OrderController',
            [
                'id' => self::RESOURCE_ID,
                'only' => $only,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        );

        $childResource->tag('orders');

        return $childResource;
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        return $event->orders();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $eventId = $request->route(self::PARENT_RESOURCE_ID);
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
     * Transform a resource into (an existing?) entity.
     * @param RESTResource $resource
     * @param Context $context
     * @param mixed|null $existingEntity
     * @param ResourceDefinitionContract|null $resourceDefinition
     * @param \CatLab\Charon\Interfaces\EntityFactory|null $entityFactory
     * @return mixed
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function toEntity(
        RESTResource $resource,
        Context $context,
        $existingEntity = null,
        $resourceDefinition = null,
        $entityFactory = null
    ) {
        $entityFactory = $entityFactory ?? new OrderEntityFactory();

        return $this->resourceTransformer->toEntity(
            $resource,
            $entityFactory,
            $context,
            $existingEntity
        );
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
}
