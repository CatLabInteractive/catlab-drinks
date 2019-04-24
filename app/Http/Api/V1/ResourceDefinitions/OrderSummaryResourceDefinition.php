<?php


namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\OrderSummary;
use App\Models\OrderSummaryItem;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class OrderSummaryResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class OrderSummaryResourceDefinition extends ResourceDefinition
{
    /**
     * OrderSummaryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(OrderSummary::class);

        $this->field('amount')
            ->visible(true);

        $this->field('totalSales')
            ->visible(true);

        $this->field('startDate')
            ->datetime()
            ->visible(true);

        $this->field('endDate')
            ->datetime()
            ->visible(true);

        $this->relationship('items', OrderSummaryItemResourceDefinition::class)
            ->expanded()
            ->visible(true);
    }
}