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

use App\Http\ManagementApi\V1\ResourceDefinitions\DeviceConnectClaimResourceDefinition;
use App\Http\ManagementApi\V1\ResourceDefinitions\DeviceConnectRequestResourceDefinition;
use App\Models\Device;
use App\Models\DeviceConnectRequest;
use App\Models\Organisation;
use App\Models\DeviceConnectClaim;

use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Shared\V1\Controllers\Base\ResourceController;

/**
 * Class DeviceConnectController
 *
 * Flow of a device connect request:
 * 1. An admin creates a connect request
 * 2. A device claims the connect request by either scanning a QR code or entering a pairing code
 *  - If the device was paired with the system before (= existing device id),
 *    the device can claim the connect request without any further interaction
 *  - If the device is new, the device must enter a pairing code
 *
 * @package App\Http\ManagementApi\V1\Controllers
 */
class DeviceConnectController extends ResourceController
{
	const RESOURCE_DEFINITION = DeviceConnectRequestResourceDefinition::class;

	/**
	 * DeviceConnectController constructor.
	 */
	public function __construct()
	{
		parent::__construct(self::RESOURCE_DEFINITION);
	}

	/**
	 * @param RouteCollection $routes
	 * @throws \CatLab\Charon\Exceptions\InvalidContextAction
	 */
	public static function setRoutes(RouteCollection $routes)
	{
		// First set all routes that require authentication
		$routes->group([],
			function($routes) {

				// As a manager of an organisation, I can create a connect request

				// Routes that require authentication
				$routes->group([
						'middleware' => [
							'auth:api'
						]
					], function (RouteCollection $routes) {

					$routes->post(
						'organisations/{organisation}/device-connect-requests',
						'DeviceConnectController@createConnectRequest'
					)	->summary('Create a new connect request')
						->parameters()->path('organisation')->required()->describe('The organisation id')
						->returns()->one(DeviceConnectRequestResourceDefinition::class);

					$routes->post(
						'device-connect-requests/{token}/ping',
						'DeviceConnectController@ping'
					)	->summary('Ping a connect request')
						->parameters()->path('token')->required()->describe('The connect token to ping')
						->returns()->one(DeviceConnectRequestResourceDefinition::class);

					$routes->post(
						'device-connect-requests/{token}/pair',
						'DeviceConnectController@pair'
					)	->summary('Pair a connect request')
						->parameters()->path('token')->required()->describe('The connect token to ping')
						->parameters()->resource(DeviceConnectRequestResourceDefinition::class)->required()->describe('The device description')
						->returns()->one(DeviceConnectRequestResourceDefinition::class);

				});

				// A device can claim a connect request
				$routes->post('device-connect', 'DeviceConnectController@claimConnectRequest')
					->summary('Get and claim a connect request')
					->parameters()->resource(DeviceConnectClaimResourceDefinition::class)->required()->describe('The device description');

			}
		)->tag('device-connect-requests');
	}

	/**
	 * Create a connect request (comparable to an authorization request)
	 * @return void
	 */
	public function createConnectRequest(Request $request, Organisation $organisation)
	{
		$this->authorize('connectDevice', Device::class, $organisation);

		$connectRequest = new \App\Models\DeviceConnectRequest();
		$connectRequest->token = \Illuminate\Support\Str::random(32);
		$connectRequest->organisation()->associate($organisation);
		$connectRequest->createdBy()->associate(\Auth::user());

		$connectRequest->expires_at = \Carbon\Carbon::now()->addMinutes(2);
		$connectRequest->save();

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($connectRequest, $context);

		return new ResourceResponse($resource);

	}

	/**
	 * @param Request $request
	 * @param string $token
	 * @return mixed
	 */
	public function ping(Request $request, string $token)
	{
		$connectRequest = DeviceConnectRequest::where('token', $token)->firstOrFail();
		$connectRequest->expires_at = \Carbon\Carbon::now()->addMinutes(2);
		$connectRequest->save();

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($connectRequest, $context);

		return new ResourceResponse($resource);
	}

	/**
	 * Claim a connect request
	 */
	public function claimConnectRequest(Request $request)
	{
		$writeContext = $this->getContext(Action::CREATE);
		$inputResources = $this->resourceTransformer->fromInput(
			DeviceConnectClaimResourceDefinition::class,
			$writeContext,
			$request
		);

		if (count($inputResources) !== 1) {
			$messages = new MessageCollection();
			$messages->add(new Message('Expected exactly one resource.'));

			throw ResourceValidationException::make($messages);
		}

		/** @var \CatLab\Charon\Models\RESTResource $inputResource */
		$inputResource = $inputResources[0];
		$inputResource->validate($writeContext);

		/** @var DeviceConnectClaim $claim */
		$claim = $this->toEntity($inputResource, $writeContext);

		// Find the connect request
		/** @var DeviceConnectRequest $connectRequest */
		$connectRequest = DeviceConnectRequest::where('token', $claim->token)->firstOrFail();

		// Check if the connect request is still valid
		if ($connectRequest->expires_at < \Carbon\Carbon::now()) {

			$messages = new MessageCollection();
			$messages->add(new Message('Connect request has expired.'));

			throw ResourceValidationException::make($messages);
		}

		/** @var Device $device */
		try {
			$device = $connectRequest->processClaim($claim);
			if ($device) {
				// Create a new access token for the device
				// This can't be passport as passport doesn't support multiple providers.
				$claim->access_token = $device->createAccessToken($connectRequest->createdBy)->access_token;

			}
		} catch (\LogicException $e) {
			$messages = new MessageCollection();
			$messages->add(new Message($e->getMessage()));

			throw ResourceValidationException::make($messages);
		}

		// Return the current state of the claim.
		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($claim, $context, DeviceConnectClaimResourceDefinition::class);
		return new ResourceResponse($resource);
	}

	/**
	 * Pair a connect request
	 */
	public function pair(Request $request, string $token)
	{
		$writeContext = $this->getContext(Action::CREATE);
		$inputResources = $this->resourceTransformer->fromInput(
			DeviceConnectRequestResourceDefinition::class,
			$writeContext,
			$request
		);

		if (count($inputResources) !== 1) {
			$messages = new MessageCollection();
			$messages->add(new Message('Expected exactly one resource.'));

			throw ResourceValidationException::make($messages);
		}

		/** @var \CatLab\Charon\Models\RESTResource $inputResource */
		$inputResource = $inputResources[0];
		$inputResource->validate($writeContext);

		/** @var DeviceConnectClaim $claim */
		$pair = $this->toEntity($inputResource, $writeContext);

		// Find the connect request
		/** @var DeviceConnectRequest $connectRequest */
		$connectRequest = DeviceConnectRequest::where('token', $token)->firstOrFail();

		// Check if the connect request is still valid
		if ($connectRequest->expires_at < \Carbon\Carbon::now()) {

			$messages = new MessageCollection();
			$messages->add(new Message('Connect request has expired.'));

			throw ResourceValidationException::make($messages);
		}

		$pairingCode = $pair->pairing_code;
		$deviceName = $pair->device_name;

		$connectRequest->verifyPairingCode($pairingCode, $deviceName);

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($connectRequest, $context);

		return new ResourceResponse($resource);
	}
}
