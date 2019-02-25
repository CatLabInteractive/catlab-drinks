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

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\MenuItemResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Event;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\ResourceResponse;

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
        /** @var Event $project */
        $project = \Request::input('event');
        $context = $this->getContext(Action::VIEW);

        $menuItems = $this->getModels($project->menuItems(), $context);

        $resources = $this->toResources($menuItems, $context, MenuItemResourceDefinition::class);
        return new ResourceResponse($resources, $context);
    }

    /**
     *
     */
    public function order()
    {

    }
}