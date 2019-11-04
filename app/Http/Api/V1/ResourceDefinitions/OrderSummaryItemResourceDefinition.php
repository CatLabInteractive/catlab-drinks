<?php


namespace App\Http\Api\V1\ResourceDefinitions;

use App\Models\OrderSummary;
use App\Models\OrderSummaryItem;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class OrderSummaryResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class OrderSummaryItemResourceDefinition extends ResourceDefinition
{
    /**
     * OrderSummaryResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(OrderSummaryItem::class);

        $this->field('amount')
            ->visible(true);

        $this->field('price')
            ->visible(true);

        $this->field('totalSales')
            ->visible(true);

        $this->field('startDate')
            ->datetime()
            ->visible(true);

        $this->field('endDate')
            ->datetime()
            ->visible(true);

        $this->relationship('menuItem', MenuItemResourceDefinition::class)
            ->one()
            ->expanded()
            ->visible(true);
    }
}
