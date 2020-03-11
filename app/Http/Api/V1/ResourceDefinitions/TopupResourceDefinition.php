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
use App\Models\Topup;
use CatLab\Charon\Models\ResourceDefinition;

/**
 * Class TopupResourceDefinition
 * @package App\Http\Api\V1\ResourceDefinitions
 */
class TopupResourceDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Topup::class);

        $this->identifier('id');

        $this->field('type')
            ->string()
            ->visible(true, true);

        $this->field('uid')
            ->string()
            ->visible(true, true);

        $this->field('amount')
            ->number()
            ->writeable(true, true)
            ->visible(true, true);

        $this->field('reason')
            ->string()
            ->writeable(true, true)
            ->visible(true, true);

        $this->field('created_at')
            ->datetime(DateTimeTransformer::class)
            ->visible(true);

        $this->field('updated_at')
            ->datetime(DateTimeTransformer::class)
            ->visible(true);

        $this->relationship('card', CardResourceDefinition::class)
            ->one()
            ->expandable()
            ->visible(true, true);

        $this->relationship('createdBy', UserResourceDefinition::class)
            ->one()
            ->expandable()
            ->visible(true, true);
    }
}
