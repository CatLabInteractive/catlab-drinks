<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\ResourceDefinitions\UserResourceDefinition;
use App\Models\User;
use Auth;
use CatLab\Charon\Collections\RouteCollection;

/**
 * Class UserController
 * @package App\Http\Api\V1\Controllers
 */
class UserController extends Base\ResourceController
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