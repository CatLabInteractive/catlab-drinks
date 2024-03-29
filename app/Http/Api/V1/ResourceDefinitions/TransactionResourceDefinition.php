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

namespace App\Http\Api\V1\ResourceDefinitions;

use App\Http\Api\V1\Transformers\DateTimeTransformer;
use App\Models\Card;
use App\Models\Transaction;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class TransactionResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class TransactionResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Transaction::class);

        $this->identifier('id')
            ->sortable();

        // the readable 'type' attribute (can have 'unknown' transactions)
        $this->field('transaction_type')
            ->display('type')
            ->string()
            ->enum([
                Transaction::TYPE_SALE,
                Transaction::TYPE_TOPUP,
                Transaction::TYPE_REFUND,
                Transaction::TYPE_UNKNOWN
            ])
            ->visible(true);

        // the writeable 'type' attribute
        $this->field('transaction_type')
            ->display('type')
            ->string()
            ->enum([
                Transaction::TYPE_SALE,
                Transaction::TYPE_TOPUP,
                Transaction::TYPE_REFUND,
                Transaction::TYPE_REVERSAL
            ])
            ->writeable(true, false);

        $this->field('value')
            ->int()
            ->visible(true)
            ->writeable(true, false)
            ->required();

        $this->field('card_sync_id')
            ->display('card_transaction')
            ->sortable()
            ->int()
            ->writeable(true, true)
            ->visible(true);

        $this->field('card_uid')
            ->display('card')
            ->string()
            ->writeable(true, false);

        $this->field('has_synced')
            ->writeable(true, true)
            ->visible(true);

        $this->field('topup_uid')
            ->writeable(true, false)
            ->max(36)
            ->visible(true)
            ->string();

        $this->field('order_uid')
            ->writeable(true, false)
            ->max(36)
            ->visible(true)
            ->string();

        $this->field('client_date')
            ->display('card_date')
            ->datetime(DateTimeTransformer::class)
            ->writeable(true, true)
            ->visible(true);

        $this->field('created_at')
            ->datetime(DateTimeTransformer::class)
            ->visible(true);

        $this->field('updated_at')
            ->datetime(DateTimeTransformer::class)
            ->visible(true);

        $this->relationship('card', CardResourceDefinition::class)
            ->visible(true)
            ->one()
            ->expandable(Action::INDEX);

        $this->relationship('order', OrderResourceDefinition::class)
            ->visible()
            ->one()
            ->expandable(Action::INDEX);

        $this->relationship('topup', TopupResourceDefinition::class)
            ->visible()
            ->one()
            ->expandable(Action::INDEX);
    }
}
