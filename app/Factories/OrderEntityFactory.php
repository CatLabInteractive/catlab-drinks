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

namespace App\Factories;

use App\Models\Event;
use App\Models\MenuItem;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Models\Identifier;
use Exception;
use InvalidArgumentException;

/**
 * Class OrderEntityFactory
 *
 * Creates entity within a given event.
 *
 * @package App\Factories
 */
class OrderEntityFactory extends \CatLab\Charon\Factories\EntityFactory
{
    /**
     * @var Event
     */
    protected $event;

    /**
     * OrderEntityFactory constructor.
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * @param string $entityClassName
     * @param Identifier $identifier
     * @param Context $context
     * @return mixed
     */
    public function resolveFromIdentifier(string $entityClassName, Identifier $identifier, Context $context)
    {
        if ($entityClassName === MenuItem::class) {
            return $this->event->menuItems()->where('id', '=', $identifier->toArray()['id'])->first();
        }

        throw new InvalidArgumentException(self::class . ' cannot link ' . $entityClassName . ' entities');
    }

    /**
     * @param $parent
     * @param $entityClassName
     * @param array $identifiers
     * @param Context $context
     * @return mixed
     * @throws Exception
     */
    public function resolveLinkedEntity($parent, string $entityClassName, array $identifiers, Context $context)
    {
        if ($entityClassName === MenuItem::class) {
            return $this->event->menuItems()->where('id', '=', $identifiers['id'])->first();
        }

        throw new InvalidArgumentException(self::class . ' cannot link ' . $entityClassName . ' entities');
    }

}