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

use App\Models\Device;
use App\Models\Event;
use CatLab\Charon\Models\ResourceDefinition;
use CatLab\Charon\Transformers\ScalarTransformer;
use CatLab\Requirements\Enums\PropertyType;

/**
 * Class DeviceResourceDefinition
 * @package App\Http\ManagementApi\V1\ResourceDefinitions
 */
class DeviceResourceDefinition extends ResourceDefinition
{
    /**
     * DeviceResourceDefinition constructor.
	 *
	 * WARNING!
	 * This resource may NEVER expose the following properties:
	 * - secret_key
	 *
	 * These keys should only be known to the device itself.
	 *
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     */
    public function __construct()
    {
        parent::__construct(Device::class);

        $this
            ->identifier('id')
            ->int();

        $this->field('name')
            ->required()
            ->visible(true)
            ->writeable(true, true);

        $this->field('uid')
            ->string()
            ->visible(true)
            ->writeable(false);

        $this->field('license_key')
            ->string()
            ->visible(true)
            ->writeable(true, true);

        $this->field('last_ping')
            ->datetime()
            ->visible(true)
            ->writeable(false);

        $this->field('isOnline')
            ->display('is_online')
            ->bool()
            ->visible(true)
            ->writeable(false);

        $this->field('category_filter_id')
            ->number()
            ->visible(true)
            ->writeable(true, true);

        $this->field('allow_remote_orders')
            ->bool()
            ->visible(true)
            ->writeable(true, true);

        $this->field('allow_live_orders')
            ->bool()
            ->visible(true)
            ->writeable(true, true);

        $this->field('pendingOrdersCount')
            ->display('pending_orders_count')
            ->number()
            ->visible(true)
            ->writeable(false);
    }
}
