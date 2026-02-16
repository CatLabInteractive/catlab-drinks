<?php

namespace App\Http\Shared\V1\ResourceDefinitions;

use App\Http\ManagementApi\V1\ResourceDefinitions\MenuItemResourceDefinition;
use App\Models\OrderSummaryItem;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class OrderSummaryItemResourceDefinition
 * @package App\Http\Shared\V1\ResourceDefinitions
 */
class OrderSummaryItemResourceDefinition extends ResourceDefinition
{
    /**
     * OrderSummaryItemResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(OrderSummaryItem::class);

        $this->field('name')
            ->visible(true);

        $this->field('amount')
            ->visible(true);

        $this->field('price')
            ->visible(true);

        $this->field('totalSales')
            ->visible(true);

        $this->field('net_total')
            ->visible(true);

        $this->field('vat_total')
            ->visible(true);

        $this->field('vat_percentage')
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
