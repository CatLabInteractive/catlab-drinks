<?php

namespace App\Http\DeviceApi\V1\ResourceDefinitions;

use App\Models\StrandedOrdersSummary;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class StrandedOrdersSummaryResourceDefinition
 * @package App\Http\DeviceApi\V1\ResourceDefinitions
 */
class StrandedOrdersSummaryResourceDefinition extends ResourceDefinition
{
	public function __construct()
	{
		parent::__construct(StrandedOrdersSummary::class);

		$this->field('count')
			->number()
			->visible(true);
	}
}
