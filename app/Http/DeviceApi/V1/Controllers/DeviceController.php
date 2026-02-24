<?php

namespace App\Http\DeviceApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\DeviceResourceDefinition;
use App\Http\DeviceApi\V1\ResourceDefinitions\StrandedOrdersSummaryResourceDefinition;
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Models\Event;
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

		$routes->put('devices/current/category-filter', 'DeviceController@updateCategoryFilter')
			->summary('Update the category filter for the current device')
			->returns()->one(DeviceResourceDefinition::class);

		$routes->put('devices/current/allow-remote-orders', 'DeviceController@updateAllowRemoteOrders')
			->summary('Update the allow_remote_orders setting for the current device')
			->returns()->one(DeviceResourceDefinition::class);

		$routes->put('devices/current/allow-live-orders', 'DeviceController@updateAllowLiveOrders')
			->summary('Update the allow_live_orders setting for the current device')
			->returns()->one(DeviceResourceDefinition::class);

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
	 * Update the category filter for the current device.
	 * @param Request $request
	 * @return mixed
	 */
	public function updateCategoryFilter(Request $request)
	{
		$device = \Auth::user();

		$categoryFilterId = $request->input('category_filter_id');
		$device->category_filter_id = $categoryFilterId ?: null;
		$device->save();

		// Re-evaluate order assignments for all events in this organisation,
		// passing the changed device so its non-matching orders get reassigned
		$events = Event::where('organisation_id', $device->organisation_id)->get();
		$assignmentService = new OrderAssignmentService();
		foreach ($events as $event) {
			$assignmentService->reevaluateAssignments($event, $device);
		}

		$readContext = $this->getContext(Action::VIEW);
		$resource = $this->toResource($device, $readContext);

		return $this->getResourceResponse($resource, $readContext);
	}

	/**
	 * Update the allow_remote_orders setting for the current device.
	 * @param Request $request
	 * @return mixed
	 */
	public function updateAllowRemoteOrders(Request $request)
	{
		$device = \Auth::user();

		$device->allow_remote_orders = $request->boolean('allow_remote_orders');
		$device->save();

		// If remote orders were disabled, re-evaluate assignments so orders
		// assigned to this device get reassigned to other devices
		if (!$device->allow_remote_orders) {
			$events = Event::where('organisation_id', $device->organisation_id)->get();
			$assignmentService = new OrderAssignmentService();
			foreach ($events as $event) {
				$assignmentService->reevaluateAssignments($event, $device);
			}
		}

		$readContext = $this->getContext(Action::VIEW);
		$resource = $this->toResource($device, $readContext);

		return $this->getResourceResponse($resource, $readContext);
	}

	/**
	 * Update the allow_live_orders setting for the current device.
	 * @param Request $request
	 * @return mixed
	 */
	public function updateAllowLiveOrders(Request $request)
	{
		$device = \Auth::user();

		$device->allow_live_orders = $request->boolean('allow_live_orders');
		$device->save();

		$readContext = $this->getContext(Action::VIEW);
		$resource = $this->toResource($device, $readContext);

		return $this->getResourceResponse($resource, $readContext);
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

}
