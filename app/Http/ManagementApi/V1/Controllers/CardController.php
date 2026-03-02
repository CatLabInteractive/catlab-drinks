<?php

namespace App\Http\ManagementApi\V1\Controllers;

use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Exceptions\InvalidContextAction;

/**
 * Class CardController
 *
 * Management API transaction controller. Extends the shared CardController
 * with read-only access (index, view) and organisation-scoped listing.
 *
 * @package App\Http\ManagementApi\V1\Controllers
 */
class CardController extends \App\Http\Shared\V1\Controllers\CardController
{
	/**
	 * @param RouteCollection $routes
	 * @throws InvalidContextAction
	 */
	public static function setRoutes(RouteCollection $routes): void
	{
		parent::setSharedRoutes($routes, ['index', 'view']);
	}
}
