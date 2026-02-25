<?php

namespace App\Http\ManagementApi\V1\Controllers;

use CatLab\Charon\Collections\RouteCollection;

/**
 * Class TransactionController
 *
 * Management API transaction controller. Extends the shared transaction controller
 * with read-only access (index, view) and organisation-scoped listing.
 *
 * @package App\Http\ManagementApi\V1\Controllers
 */
class TransactionController extends \App\Http\Shared\V1\Controllers\TransactionController
{
	/**
	 * @param RouteCollection $routes
	 * @throws \CatLab\Charon\Exceptions\InvalidContextAction
	 */
	public static function setRoutes(RouteCollection $routes)
	{
		static::setSharedRoutes($routes, ['index', 'view']);
	}
}
