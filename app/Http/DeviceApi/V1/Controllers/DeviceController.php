<?php

namespace App\Http\DeviceApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\DeviceResourceDefinition;
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Exceptions\InvalidEntityException;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Exceptions\InvalidResourceDefinition;
use CatLab\Charon\Exceptions\InvalidTransformer;
use CatLab\Charon\Exceptions\IterableExpected;
use CatLab\Charon\Exceptions\VariableNotFoundInContext;
use CatLab\Charon\Laravel\Controllers\CrudController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class DeviceController extends ResourceController {

	use CrudController;

	public function __construct()
	{
		parent::__construct(DeviceResourceDefinition::class);
	}

    /**
     * @param RouteCollection $routes
     * @return void
     * @throws InvalidContextAction
     */
	public static function setRoutes(RouteCollection $routes) {

		$routes->get('devices/current', 'DeviceController@currentDevice')
			->returns()->one(DeviceResourceDefinition::class);

	}

    /**
     * Return the current device.
     * @param Request $request action
     * @return mixed
     * @throws InvalidContextAction
     * @throws InvalidEntityException
     * @throws InvalidPropertyException
     * @throws InvalidResourceDefinition
     * @throws InvalidTransformer
     * @throws IterableExpected
     * @throws VariableNotFoundInContext
     * @throws AuthorizationException
     */
	public function currentDevice(Request $request)
	{
		$entity = \Auth::user();

        $this->authorizeView($request, $entity);

		$readContext = $this->getContext(Action::VIEW);
		$readContext->setParameter('can_view_secret', \Auth::user()->can('viewSecret', $entity));

		$resource = $this->toResource($entity, $readContext);

        return $this->getResourceResponse($resource, $readContext);
	}

}
