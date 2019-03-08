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

use App\Factories\EntityFactory;
use App\Factories\OrderEntityFactory;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\MenuItemResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Event;
use App\Models\Order;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\ResourceResponse;
use Illuminate\Http\JsonResponse;

/**
 * Class PublicController
 *
 * This controller is behind a special authentication middleware that
 * injects an event in the request.
 *
 * @package App\Http\Api\V1\Controllers
 */
class PublicController extends ResourceController
{
    public function __construct()
    {
        parent::__construct(MenuItemResourceDefinition::class);
    }

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->group(
            [
                'tags' => 'public'
            ],
            function(RouteCollection $routes) {

                $routes->get('public/menu', 'PublicController@menu')
                    ->returns()->many(MenuItemResourceDefinition::class);

                $routes->post('public/order', 'PublicController@order')
                    ->parameters()->resource(OrderResourceDefinition::class)->one()
                    ->returns()->many(MenuItemResourceDefinition::class);

            }
        );
    }

    /**
     * Get all available menu items.
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     */
    public function menu()
    {
        /** @var Event $event */
        $event = \Request::input('event');

        // Check if the bar is actually open.
        if (!$event->isOpen()) {
            return new JsonResponse([
                'error' => [
                    'message' => 'De bar aanvaardt momenteel geen bestellingen.'
                ]
            ], 423);
        }

        $context = $this->getContext(Action::VIEW);

        $menuItems = $this->getModels($event->menuItems()->where('is_selling', '=', true), $context);

        $resources = $this->toResources($menuItems, $context, MenuItemResourceDefinition::class);
        return new ResourceResponse($resources, $context);
    }

    /**
     *
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public function order()
    {
        /** @var Event $event */
        $event = \Request::input('event');

        // Check if the bar is actually open.
        if (!$event->isOpen()) {
            return new JsonResponse([
                'error' => [
                    'message' => 'De bar aanvaardt momenteel geen bestellingen. Probeer het later nog eens.'
                ]
            ], 423);
        }

        // Process the order
        $context = $this->getContext(Action::CREATE);

        $bodyResource = $this->bodyToResource($context, OrderResourceDefinition::class);

        /** @var Order $entity */
        $entity = $this->toEntity(
            $bodyResource,
            $context,
            null,
            OrderResourceDefinition::class,
            new OrderEntityFactory($event)
        );

        $entity->event()->associate($event);

        $entity->saveRecursively();

        $readContext = $this->getContext(Action::VIEW);
        $resource = $this->toResource($entity, $readContext, OrderResourceDefinition::class);
        return new ResourceResponse($resource, $readContext);
    }
}