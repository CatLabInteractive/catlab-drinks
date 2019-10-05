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

use App\Http\Api\V1\ResourceDefinitions\OrganisationResourceDefinition;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Http\Request;

/**
 * Class OrganisationController
 * @package App\Http\Api\V1\Controllers
 */
class OrganisationController extends Base\ResourceController
{
    const RESOURCE_DEFINITION = OrganisationResourceDefinition::class;
    const RESOURCE_ID = 'organisation';
    const PARENT_RESOURCE_ID = 'user';

    use \CatLab\Charon\Laravel\Controllers\CrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $resource = $routes->resource(
            self::RESOURCE_DEFINITION,
            'organisations',
            'OrganisationController',
            [
                'id' => self::RESOURCE_ID,
            ]
        );

        $resource->tag('organisations');
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);
    }
}
