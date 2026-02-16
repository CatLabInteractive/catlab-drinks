<?php

namespace App\Http\DeviceApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\EventResourceDefinition;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class EventController extends \App\Http\Shared\V1\Controllers\EventController
{
	const RESOURCE_DEFINITION = EventResourceDefinition::class;

	public static function setRoutes(RouteCollection $routes, array $only = [
		'index', 'view', 'edit'
	]): \CatLab\Charon\Collections\RouteCollection
	{
		return parent::setRoutes($routes, $only);
	}

	/**
	 * Checks if user is authorized to edit the entity.
	 * @param Request $request
	 * @param $entity
	 * @throws AuthorizationException
	 */
	protected function authorizeEdit(Request $request, $entity)
	{
		$this->authorize('editStatus', $entity);
	}
}
