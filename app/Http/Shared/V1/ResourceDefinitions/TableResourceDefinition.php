<?php

namespace App\Http\Shared\V1\ResourceDefinitions;

use App\Models\Table;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class TableResourceDefinition
 * @package App\Http\Shared\V1\ResourceDefinitions
 */
class TableResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Table::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('table_number')
            ->number()
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('name')
            ->string()
            ->required()
            ->visible(true)
            ->writeable(true, true);
    }
}
