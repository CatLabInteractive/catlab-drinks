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

namespace App\Http\ManagementApi\V1\ResourceDefinitions;

use App\Models\Event;
use App\Models\MenuItem;
use CatLab\Charon\Models\ResourceDefinition;
use CatLab\Charon\Transformers\ScalarTransformer;
use CatLab\Requirements\Enums\PropertyType;

/**
 * Class MenuItemResourceDefinition
 * @package App\Http\ManagementApi\V1\ResourceDefinitions
 */
class MenuItemResourceDefinition extends ResourceDefinition
{
    /**
     * EventResourceDefinition constructor.
     */
    public function __construct()
    {
        parent::__construct(MenuItem::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('name')
            ->string()
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('description')
            ->string()
            ->visible(true)
            ->writeable(true, true);

        $this->field('price')
            ->number()
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('vat_percentage')
            ->number()
            ->visible(true)
            ->writeable(true, true);

        $this->field('is_selling')
            ->bool()
            ->visible(true)
            ->writeable(true, true);

        $this->relationship('category', CategoryResourceDefinition::class)
            ->one()
            ->visible(true)
            ->expanded()
            ->linkable();


    }
}
