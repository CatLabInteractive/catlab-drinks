<?php

namespace App\Http\ManagementApi\V1\ResourceDefinitions;

use App\Models\DeviceConnectRequest;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * @package App\Http\ManagementApi\V1\ResourceDefinitions
 */
class DeviceConnectRequestResourceDefinition extends ResourceDefinition
{
	/**
	 * Warning!
	 * This resource may NEVER expose the following properties:
	 * 
	 * - pairing_code
	 * 
	 * These keys should only be known to the device itself.
	 * 
	 * @return void 
	 */
	public function __construct()
	{
		parent::__construct(DeviceConnectRequest::class);

		$this->field('token')
			->string()
			->required()
			->visible(true)
			->writeable(false, false);

		$this->field('state')
			->enum([
				DeviceConnectRequest::STATE_PENDING,
				DeviceConnectRequest::STATE_ACCEPTED,
				DeviceConnectRequest::STATE_REQUIRES_PAIRING_CODE
			])
			->visible(true);

		$this->field('url')
			->visible(true);

		$this->field('pairing_code')
			->string()
			->writeable()
			->required();

		$this->field('device_name')
			->string()
			->writeable()
			->required();
	}
}