<?php

namespace App\Http\DeviceApi\V1\ResourceDefinitions;

use App\Models\Organisation;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * POS-facing resource definition for devices.
 * 
 * @package App\Http\DeviceApi\V1\ResourceDefinitions
 */
class OrganisationResourceDefinition extends ResourceDefinition
{
	public function __construct()
	{
		parent::__construct(Organisation::class);

		$this->identifier('id')
			->int();

		$this->field('name')
			->string()
			->required();
	}
}