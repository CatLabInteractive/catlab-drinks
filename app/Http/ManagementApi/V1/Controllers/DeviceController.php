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

use App\Http\ManagementApi\V1\ResourceDefinitions\DeviceResourceDefinition;
use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;

use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Shared\V1\Controllers\Base\ResourceController;

/**
 * Class DeviceController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class DeviceController extends ResourceController
{
	const RESOURCE_DEFINITION = DeviceResourceDefinition::class;
	const RESOURCE_ID = 'device';
	const PARENT_RESOURCE_ID = 'organisation';

	use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
		beforeSaveEntity as traitBeforeSaveEntity;
	}

	/**
	 * @param RouteCollection $routes
	 * @throws \CatLab\Charon\Exceptions\InvalidContextAction
	 */
	public static function setRoutes(RouteCollection $routes)
	{
		$childResource = $routes->childResource(
			static::RESOURCE_DEFINITION,
			'organisations/{' . self::PARENT_RESOURCE_ID . '}/devices',
			'devices',
			'DeviceController',
			[
				'id' => self::RESOURCE_ID,
				'parentId' => self::PARENT_RESOURCE_ID,
				'only' => [ 'index', 'view', 'edit', 'destroy' ]
			]
		);

		$childResource->tag('devices');

		$routes->post(
			'devices/{' . self::RESOURCE_ID . '}/license',
			'DeviceController@setLicense'
		)	->summary('Set a license key for a device')
			->parameters()->path(self::RESOURCE_ID)->required()->describe('The device id')
			->returns()->one(DeviceResourceDefinition::class)
			->tag('devices');

		$routes->post(
			'devices/{' . self::RESOURCE_ID . '}/approve-key',
			'DeviceController@approveKey'
		)	->summary('Approve a device\'s public key')
			->parameters()->path(self::RESOURCE_ID)->required()->describe('The device id')
			->returns()->one(DeviceResourceDefinition::class)
			->tag('devices');

		$routes->post(
			'devices/{' . self::RESOURCE_ID . '}/revoke-key',
			'DeviceController@revokeKey'
		)	->summary('Revoke a device\'s public key')
			->parameters()->path(self::RESOURCE_ID)->required()->describe('The device id')
			->returns()->one(DeviceResourceDefinition::class)
			->tag('devices');

		$routes->get(
			'organisations/{' . self::PARENT_RESOURCE_ID . '}/public-keys',
			'DeviceController@publicKeys'
		)	->summary('List all devices with public keys (including deleted)')
			->parameters()->path(self::PARENT_RESOURCE_ID)->required()->describe('The organisation id')
			->returns()->many(DeviceResourceDefinition::class)
			->tag('devices');

		$routes->get(
			'devices/{' . self::RESOURCE_ID . '}/signed-cards',
			'DeviceController@signedCards'
		)	->summary('List all cards last signed by this device')
			->parameters()->path(self::RESOURCE_ID)->required()->describe('The device id')
			->returns()->many(\App\Http\DeviceApi\V1\ResourceDefinitions\CardResourceDefinition::class)
			->tag('devices');
	}

	/**
	 * @param Request $request
	 * @return Relation
	 */
	public function getRelationship(Request $request): Relation
	{
		/** @var User $user */
		$organisation = $this->getParent($request);
		return $organisation->devices();
	}

	/**
	 * @param Request $request
	 * @return Model
	 */
	public function getParent(Request $request): Model
	{
		$organisationId = $request->route(self::PARENT_RESOURCE_ID);
		return Organisation::findOrFail($organisationId);
	}


	/**
	 * @return string
	 */
	public function getRelationshipKey(): string
	{
		return self::PARENT_RESOURCE_ID;
	}

	/**
	 * @param Request $request
	 * @param int $deviceId
	 * @return ResourceResponse
	 * @throws ResourceValidationException
	 */
	public function setLicense(Request $request, int $deviceId)
	{
		$device = Device::findOrFail($deviceId);
		$this->authorize('edit', $device);

		$licenseKey = $request->input('license_key');
		if (empty($licenseKey)) {
			$messages = new MessageCollection();
			$messages->add(new Message('Property \'license_key\' must exist.'));
			throw ResourceValidationException::make($messages);
		}

		$device->license_key = $licenseKey;
		$this->validateLicenseKey($device);
		$device->save();

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($device, $context);

		return new ResourceResponse($resource);
	}

	/**
	 * @param Request $request
	 * @param Model $entity
	 * @param bool $isNew
	 * @return Model
	 * @throws ResourceValidationException
	 */
	protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
	{
		$this->traitBeforeSaveEntity($request, $entity, $isNew);

		if ($entity->isDirty('license_key') && $entity->license_key !== null) {
			$this->validateLicenseKey($entity);
		}

		return $entity;
	}

	/**
	 * Validate that the license key's device_uid matches the device.
	 * @param Device $device
	 * @throws ResourceValidationException
	 */
	private function validateLicenseKey(Device $device)
	{
		$decoded = base64_decode($device->license_key, true);
		if ($decoded === false) {
			$messages = new MessageCollection();
			$messages->add(new Message('Invalid license key: not valid base64.'));
			throw ResourceValidationException::make($messages);
		}

		$license = json_decode($decoded, true);
		if (!is_array($license) || !isset($license['data'])) {
			$messages = new MessageCollection();
			$messages->add(new Message('Invalid license key: invalid license structure.'));
			throw ResourceValidationException::make($messages);
		}

		if (!isset($license['signature']) || empty($license['signature'])) {
			$messages = new MessageCollection();
			$messages->add(new Message('Invalid license key: missing signature.'));
			throw ResourceValidationException::make($messages);
		}

		$data = $license['data'];
		if (!is_array($data) || !isset($data['device_uid'])) {
			$messages = new MessageCollection();
			$messages->add(new Message('Invalid license key: missing device_uid in license data.'));
			throw ResourceValidationException::make($messages);
		}

		if ($data['device_uid'] !== $device->uid) {
			$messages = new MessageCollection();
			$messages->add(new Message('Invalid license key: device_uid does not match this device.'));
			throw ResourceValidationException::make($messages);
		}

		if (isset($data['expiration_date']) && $data['expiration_date'] !== null) {
			$expirationDate = strtotime($data['expiration_date']);
			if ($expirationDate === false) {
				$messages = new MessageCollection();
				$messages->add(new Message('Invalid license key: invalid expiration_date format.'));
				throw ResourceValidationException::make($messages);
			}

			if ($expirationDate < time()) {
				$messages = new MessageCollection();
				$messages->add(new Message('Invalid license key: license has expired.'));
				throw ResourceValidationException::make($messages);
			}
		}
	}

	/**
	 * Approve a device's public key.
	 * @param Request $request
	 * @param int $deviceId
	 * @return ResourceResponse|JsonResponse
	 */
	public function approveKey(Request $request, int $deviceId)
	{
		$device = Device::withTrashed()->findOrFail($deviceId);
		$this->authorize('approveKey', $device);

		if (empty($device->public_key)) {
			return new JsonResponse([
				'error' => [
					'message' => 'Device has no public key to approve.'
				]
			], 422);
		}

		/** @var User $user */
		$user = \Auth::user();
		$device->approvePublicKey($user);

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($device, $context);
		return new ResourceResponse($resource);
	}

	/**
	 * Revoke a device's public key.
	 * @param Request $request
	 * @param int $deviceId
	 * @return ResourceResponse|JsonResponse
	 */
	public function revokeKey(Request $request, int $deviceId)
	{
		$device = Device::withTrashed()->findOrFail($deviceId);
		$this->authorize('revokeKey', $device);

		$device->revokePublicKey();

		$context = $this->getContext(Action::VIEW);
		$resource = $this->toResource($device, $context);
		return new ResourceResponse($resource);
	}

	/**
	 * List all devices with public keys (including soft-deleted).
	 * @param Request $request
	 * @param int $organisationId
	 * @return ResourceResponse
	 */
	public function publicKeys(Request $request, int $organisationId)
	{
		$organisation = Organisation::findOrFail($organisationId);
		$this->authorize('viewPublicKeys', [Device::class, $organisation]);

		$devices = $organisation->allDevices()
			->whereNotNull('public_key')
			->get();

		$context = $this->getContext(Action::INDEX);
		$resources = $this->toResources($devices, $context);
		return new ResourceResponse($resources, $context);
	}

	/**
	 * List all cards last signed by a specific device.
	 * @param Request $request
	 * @param int $deviceId
	 * @return ResourceResponse
	 */
	public function signedCards(Request $request, int $deviceId)
	{
		$device = Device::withTrashed()->findOrFail($deviceId);
		$this->authorize('view', $device);

		$cards = $device->signedCards;

		$context = $this->getContext(Action::INDEX);
		$resources = $this->toResources(
			$cards,
			$context,
			\App\Http\DeviceApi\V1\ResourceDefinitions\CardResourceDefinition::class
		);
		return new ResourceResponse($resources, $context);
	}
}
