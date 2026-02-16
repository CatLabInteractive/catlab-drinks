<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Http\DeviceApi\V1\ResourceDefinitions;

use App\Http\ManagementApi\V1\ResourceDefinitions\OrganisationResourceDefinition;
use App\Http\Shared\V1\Transformers\DateTimeTransformer;
use App\Models\Card;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class CardResourceDefinition
 * @package App\Http\ManagementApi\V1\ResourceDefinitions
 */
class CardResourceDefinition extends ResourceDefinition
{
    /**
     * EventResourceDefinition constructor.
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     */
    public function __construct()
    {
        parent::__construct(Card::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('uid')
            ->visible(true, true)
            ->string()
            ->writeable(true, false);

        $this->field('name')
            ->visible()
            ->string();

        $this->field('balance')
            ->visible(true, true)
            ->number();

        $this->relationship('organisation', OrganisationResourceDefinition::class)
            ->one()
            ->expandable()
            ->visible();

        $this->field('transaction_count')
            ->display('transactions')
            ->visible(true, true);

        $this->relationship('pendingTransactions', TransactionResourceDefinition::class)
            ->expanded()
            ->visible();

        $this->field('orderTokenAliases')
            ->string()
            ->visible(true)
            ->array()
            ->writeable(true, true);

        $this->field('discount_percentage')
            ->display('discount')
            ->number()
            ->min(0)
            ->max(100)
            ->visible(true, true)
            ->writeable(true, true);

        $this->field('updated_at')
            ->visible()
            ->datetime(DateTimeTransformer::class)
            ->sortable();
    }
}
