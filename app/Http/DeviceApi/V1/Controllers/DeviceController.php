<?php

namespace App\Http\DeviceApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\DeviceResourceDefinition;
use App\Http\DeviceApi\V1\ResourceDefinitions\StrandedOrdersSummaryResourceDefinition;
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\StrandedOrdersSummary;
use App\Services\OrderAssignmentService;
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
use CatLab\Requirements\Exceptions\ResourceValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class DeviceController extends ResourceController {

	use CrudController {
		beforeSaveEntity as traitBeforeSaveEntity;
	}

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

		$routes->put('devices/current', 'DeviceController@updateCurrentDevice')
			->summary('Update settings for the current device')
			->returns()->one(DeviceResourceDefinition::class);

		$routes->get('organisations/{organisation}/approved-public-keys', 'DeviceController@approvedPublicKeys')
			->summary('Get all approved public keys for the organisation')
			->parameters()->path('organisation')->required()
			->tag('devices');

		$routes->get('events/{event}/stranded-orders', 'DeviceController@strandedOrders')
			->summary('Check for orders that cannot be processed by any online POS')
			->returns()->one(StrandedOrdersSummaryResourceDefinition::class);

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

	/**
	 * Update settings for the current device.
	 * Uses the resource definition to parse writeable fields from the request body.
	 * Reassignment logic is handled by the Device model's updated event.
	 * @param Request $request
	 * @return mixed
	 */
	public function updateCurrentDevice(Request $request)
	{
		$device = \Auth::user();

		$this->authorizeEdit($request, $device);

		$writeContext = $this->getContext(Action::EDIT);

		$inputResource = $this->resourceTransformer
			->fromInput($this->getResourceDefinitionFactory(), $writeContext, $request)
			->first();

		try {
			$inputResource->validate($writeContext, $device);
		} catch (ResourceValidationException $e) {
			return $this->getValidationErrorResponse($e);
		}

		$device = $this->toEntity($inputResource, $writeContext, $device);

		try {
			$device = $this->saveEntity($request, $device);
		} catch (ResourceValidationException $e) {
			return $this->getValidationErrorResponse($e);
		}

		return $this->createViewEntityResponse($device);
	}

	/**
	 * Check for stranded orders that cannot be processed by any online POS.
	 * @param Request $request
	 * @param int $event
	 * @return mixed
	 */
	public function strandedOrders(Request $request, $event)
	{
		$event = Event::findOrFail($event);

		$assignmentService = new OrderAssignmentService();

		$summary = new StrandedOrdersSummary();
		$summary->count = $assignmentService->countStrandedOrders($event);

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($summary, $context, StrandedOrdersSummaryResourceDefinition::class);

		return $this->getResourceResponse($resource, $context);
	}

	/**
	 * Get all approved public keys for the organisation.
	 * POS devices use this to verify card signatures from other terminals.
	 * @param Request $request
	 * @param int $organisationId
	 * @return mixed
	 */
	public function approvedPublicKeys(Request $request, $organisationId)
	{
		$organisation = Organisation::findOrFail($organisationId);
		$this->authorize('viewPublicKeys', [\App\Models\Device::class, $organisation]);

		$devices = $organisation->approvedDevicesWithKeys()->get();

		$data = $devices->map(function ($device) {
			return [
				'id' => $device->id,
				'uid' => $device->uid,
				'public_key' => $device->public_key,
				'approved_at' => $device->approved_at,
			];
		});

		return response()->json(['items' => $data]);
	}

}
