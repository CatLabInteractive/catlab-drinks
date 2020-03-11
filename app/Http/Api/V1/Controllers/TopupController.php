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

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\TopupResourceDefinition;
use App\Models\Card;

use App\Models\Topup;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Laravel\Controllers\ChildCrudController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

/**
 * Class TopupController
 * @package App\Http\Api\V1\Controllers
 */
class TopupController extends ResourceController
{
    const RESOURCE_DEFINITION = TopUpResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'card';

    use ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
        afterSaveEntity as traitAfterSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            'cards/{' . self::PARENT_RESOURCE_ID . '}/topups',
            'topups',
            'TopUpController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID,
                'only' => [
                    'store', 'view'
                ]
            ]
        );

        $childResource->tag('topups');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Card $event */
        $card = $this->getParent($request);
        return $card->transactions();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $cardId = $request->route(self::PARENT_RESOURCE_ID);
        return Card::findOrFail($cardId);
    }


    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     * @return Model
     * @throws \Exception
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);

        $entity->type = Topup::TYPE_MANUAL;
        $entity->status = Topup::STATUS_PENDING;
        $entity->uid = Uuid::uuid1();

        return $entity;
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     * @return Model
     */
    protected function afterSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitAfterSaveEntity($request, $entity, $isNew);

        $entity->success();
        return $entity;
    }
}
