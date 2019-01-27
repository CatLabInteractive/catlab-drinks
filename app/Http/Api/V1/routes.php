<?php

use CatLab\Charon\Collections\RouteCollection;

/*
 * API v1
 */
$routes = new RouteCollection([
    'prefix' => '/api/v1/',
    'namespace' => 'App\Http\Api\V1\Controllers',
    'middleware' => [
        // 'cors
    ],
    'suffix' => '.{format?}',
    'security' => [
        [
            'oauth2' => [
                'full'
            ]
        ]
    ]
]);

$routes->group(
    [],
    function(RouteCollection $routes)
    {
        // All endpoints have these parameters
        $routes
            ->parameters()
            ->path('format?')->enum(['json'])->describe('Output format')->default('json');

        // All endpoints can have these return values
        $routes->returns()->statusCode(403)->describe('Authentication error');
        $routes->returns()->statusCode(404)->describe('Entity not found');

        // Swagger documentation
        $routes->get('description', 'DescriptionController@description')->tag('description');

        // Controllers: oauth middleware is required
        $routes->group(
            [
                'middleware' => [ 'auth:api' ],
            ],
            function(RouteCollection $routes)
            {
                /*
                 * List all controllers
                 */
                \App\Http\Api\V1\Controllers\UserController::setRoutes($routes);

            }
        );
    }
);

return $routes;