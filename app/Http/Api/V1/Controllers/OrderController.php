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

use App\Factories\OrderEntityFactory;
use App\Http\Api\V1\ResourceDefinitions\EventResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\MenuItemResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Auth;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Factories\ResourceFactory;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition as ResourceDefinitionContract;
use CatLab\Charon\Laravel\Factories\EntityFactory;
use CatLab\Charon\Laravel\Resolvers\PropertyResolver;
use CatLab\Charon\Laravel\Resolvers\PropertySetter;
use CatLab\Charon\Laravel\Resolvers\QueryAdapter;
use CatLab\Charon\Laravel\ResourceTransformer;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Resolvers\RequestResolver;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EventController
 * @package App\Http\Api\V1\Controllers
 */
class OrderController extends Base\ResourceController
{
    const RESOURCE_DEFINITION = OrderResourceDefinition::class;
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
        $parentPath = 'events/{' . self::PARENT_RESOURCE_ID . '}/orders';

        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            $parentPath,
            'orders',
            'OrderController',
            [
                'id' => self::RESOURCE_ID,
                'only' => [ 'index', 'view', 'edit', 'destroy' ],
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        );

        $childResource->post($parentPath, 'OrderController@store')
            ->summary(function () {
                $entityName = ResourceDefinitionLibrary::make(static::RESOURCE_DEFINITION)
                    ->getEntityName(false);

                return 'Create a new ' . $entityName;
            })
            ->parameters()->resource(static::RESOURCE_DEFINITION)->many()->required()
            ->parameters()->path(self::PARENT_RESOURCE_ID)->string()->required()
            ->returns()->statusCode(200)->many(static::RESOURCE_DEFINITION);

        $childResource->tag('orders');
    }

    /**
     * Create a new entity
     * @param Request $request
     * @return Response
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\NoInputDataFound
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function store(Request $request)
    {
        $this->request = $request;

        $this->authorizeCreate($request);

        $writeContext = $this->getContext(Action::CREATE);
        $readContext = $this->getContext(Action::INDEX);

        $inputResources = $this->bodyToResources($writeContext);
        $resources = $this->getResourceTransformer()->getResourceFactory()->createResourceCollection();

        foreach ($inputResources as $inputResource) {

            try {
                $inputResource->validate($writeContext);
            } catch (ResourceValidationException $e) {
                return $this->getValidationErrorResponse($e);
            }

            $entity = $this->toEntity($inputResource, $writeContext);

            // Look for unique identifier duplicate
            $existing = Order::where('uid', '=', $entity->uid)->first();
            if ($existing) {
                $resources[] = $this->toResource($existing, $readContext);
                continue;
            }

            // Save the entity
            $this->saveEntity($request, $entity);

            $resources[] = $this->toResource($entity, $readContext);
        }

        // Turn back into a resource
        return $this->getResourceResponse($resources, $readContext);
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
