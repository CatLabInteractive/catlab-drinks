<?php

namespace App\Http\Shared\V1\ResourceDefinitions;

use App\Http\Shared\V1\Transformers\DateTimeTransformer;
use App\Models\Patron;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class PatronResourceDefinition
 * @package App\Http\Shared\V1\ResourceDefinitions
 */
class PatronResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Patron::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('name')
            ->string()
            ->visible(true)
            ->writeable(true, true);

        $this->field('table_id')
            ->number()
            ->visible(true)
            ->writeable(true, true)
            ->filterable();

        $this->field('outstandingBalance')
            ->display('outstanding_balance')
            ->number()
            ->visible(true);

        $this->field('hasUnpaidOrders')
            ->display('has_unpaid_orders')
            ->bool()
            ->visible(true);

        $this->relationship('orders', OrderResourceDefinition::class)
            ->many()
            ->expandable()
            ->visible(true);

        $this->relationship('table', TableResourceDefinition::class)
            ->one()
            ->expandable()
            ->visible(true);

        $this->field('created_at')
            ->display('date')
            ->datetime(DateTimeTransformer::class)
            ->visible(true);
    }
}
