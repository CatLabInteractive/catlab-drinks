<?php

namespace App\Http\Shared\V1\ResourceDefinitions;

use App\Models\Device;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class DevicePublicKeyResourceDefinition
 *
 * Resource definition for approved public keys.
 * Used by POS devices to download approved keys for card verification.
 *
 * @package App\Http\Shared\V1\ResourceDefinitions
 */
class DevicePublicKeyResourceDefinition extends ResourceDefinition
{
	public function __construct()
	{
		parent::__construct(Device::class);

		$this->identifier('id')
			->int();

		$this->field('uid')
			->string()
			->visible(true)
			->writeable(false);

		$this->field('public_key')
			->string()
			->visible(true)
			->writeable(false);

		$this->field('approved_at')
			->datetime()
			->visible(true)
			->writeable(false);
	}
}
