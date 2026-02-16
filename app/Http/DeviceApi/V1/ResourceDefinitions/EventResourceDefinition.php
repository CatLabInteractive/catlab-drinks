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
use App\Models\Event;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class EventResourceDefinition
 * @package App\Http\ManagementApi\V1\ResourceDefinitions
 */
class EventResourceDefinition extends ResourceDefinition
{
    /**
     * EventResourceDefinition constructor.
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     */
    public function __construct()
    {
        parent::__construct(Event::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('name')
            ->string()
            ->required()
            ->visible(true);

        $this->field('is_selling')
            ->bool()
            ->visible(true)
            ->writeable(true, true);

        $this->field('order_token')
            ->string()
            ->visible(true);

        $this->field('orderUrl')
            ->display('order_url')
            ->string()
            ->visible(true);

        $this->field([ 'payment_cash', 'payment_vouchers', 'payment_cards', 'allow_unpaid_online_orders', 'split_orders_by_categories' ])
            ->bool()
            ->visible(true);

        $this->field('payment_voucher_value')
            ->number()
            ->visible(true);

        $this->field('checkin_url')
            ->string()
            ->visible(true);

        $this->relationship('organisation', OrganisationResourceDefinition::class)
            ->one()
            ->expandable(true)
            ->visible();
    }
}
