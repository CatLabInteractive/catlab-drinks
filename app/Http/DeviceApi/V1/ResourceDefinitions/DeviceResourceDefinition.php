<?php

namespace App\Http\DeviceApi\V1\ResourceDefinitions;

use App\Http\DeviceApi\V1\Transformers\SecretKeyTransformer;
use App\Models\Device;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * POS-facing resource definition for devices.
 *
 * @package App\Http\DeviceApi\V1\ResourceDefinitions
 */
class DeviceResourceDefinition extends ResourceDefinition
{
	public function __construct()
	{
		parent::__construct(Device::class);

		$this->identifier('id')
			->int();

		$this->field('name')
			->string()
			->required();

		$this->relationship('organisation', OrganisationResourceDefinition::class)
			->url('/organisations/{model.organisation.id}')
			->visible()
			->one()
			->expanded(Action::IDENTIFIER, Action::VIEW);

		$this->field('secret_key')
			->string()
			->transformer(SecretKeyTransformer::class)
			->visible(false)
			->writeable(false);

		$this->field('license_key')
			->string()
			->visible(true)
			->writeable(false);

	}
}
