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

use CatLab\Charon\Collections\RouteCollection;

/*
 * API v1
 */
$routes = new RouteCollection([
	'prefix' => '/pos-api/v1/',
	'namespace' => 'App\Http\DeviceApi\V1\Controllers',
	'middleware' => [
		//'cors'
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

		// Controllers: oauth middleware is required
		$routes->group(
			[
				'middleware' => [ 'auth:device' ],
			],
			function(RouteCollection $routes)
			{
				/*
				 * List all controllers
				 */
				\App\Http\DeviceApi\V1\Controllers\DeviceController::setRoutes($routes);
				\App\Http\DeviceApi\V1\Controllers\OrganisationController::setRoutes($routes);
                \App\Http\DeviceApi\V1\Controllers\CardController::setRoutes($routes);
                \App\Http\DeviceApi\V1\Controllers\TransactionController::setRoutes($routes);
				\App\Http\DeviceApi\V1\Controllers\EventController::setRoutes($routes);
				\App\Http\DeviceApi\V1\Controllers\MenuController::setRoutes($routes);

                \App\Http\DeviceApi\V1\Controllers\OrderController::setRoutes($routes);
				\App\Http\DeviceApi\V1\Controllers\CategoryController::setRoutes($routes);

				\App\Http\DeviceApi\V1\Controllers\OrderSummaryController::setRoutes($routes);

				\App\Http\DeviceApi\V1\Controllers\AttendeeController::setRoutes($routes);

			}
		);
	}
);

return $routes;
