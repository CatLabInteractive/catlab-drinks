<?php

namespace App\Http\ManagementApi\V1\ResourceDefinitions;

use App\Models\DeviceConnectClaim;
use App\Models\DeviceConnectRequest;
use CatLab\Charon\Models\ResourceDefinition;

class DeviceConnectClaimResourceDefinition extends ResourceDefinition
{
	public function __construct()
	{
		parent::__construct(DeviceConnectClaim::class);

		$this->field('token')
			->string()
			->required()
			->visible(true)
			->writeable(true, false);

		$this->field('device_uid')
			->string()
			->visible(true)
			->writeable(true, false);

		$this->field('pairing_code')
			->string()
			->visible(true);
			
		$this->field('access_token')
			->visible(true);
	}
}