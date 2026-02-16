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

use App\Http\ManagementApi\V1\ResourceDefinitions\UserResourceDefinition;
use App\Models\User;
use Auth;
use CatLab\Charon\Collections\RouteCollection;
use App\Http\Shared\V1\Controllers\Base\ResourceController;

/**
 * Class UserController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class UserController extends ResourceController
{
    const USER_ME = 'me';

    /**
     * Set all routes for this controller
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->group(function(RouteCollection $routes)
        {
            $routes->tag('users');

            $routes
                ->get('users/{id}', 'UserController@show')
                ->parameters()->path('id')->required()
                ->returns()->one(UserResourceDefinition::class)
                ->summary('Return a user object');

            $routes
                ->get('users', 'UserController@index')
                ->returns()->many(UserResourceDefinition::class)
                ->summary('Return all users');
        });
    }

    /**
     * UserController constructor.
     */
    public function __construct()
    {
        parent::__construct(UserResourceDefinition::class);
    }

    /**
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id)
    {
        $user = $this->getUser($id);
        if (!$user) {
            return $this->notFound($id, User::class);
        }

        $this->authorize('show', $user);
        return $this->output($user);
    }

    /**
     * @TODO This method only exists as an example. You do NOT want this in your production app.
     * @return \Illuminate\Http\JsonResponse
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        $this->authorize('index');
        return $this->output(User::all());
    }

    /**
     * @param string $id
     * @return mixed
     */
    private function getUser($id)
    {
        if ($id === self::USER_ME) {
            return User::find(Auth::id());
        } else {
            return User::find($id);
        }
    }
}
