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

namespace App\Http\Shared\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\CardResourceDefinition;
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Models\Card;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Transaction;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Exceptions\ResourceException;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class CardController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class CardController extends ResourceController
{
    const RESOURCE_DEFINITION = CardResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

	/**
	 * @param RouteCollection $routes
	 * @param array $only
	 * @return RouteCollection
	 * @throws InvalidContextAction
	 */
    public static function setSharedRoutes(RouteCollection $routes, array $only = ['index', 'view']): RouteCollection
    {
        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/cards',
            'cards',
            'CardController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        );

		if (in_array('view', $only)) {
			$childResource->get(
				'organisations/{' . self::PARENT_RESOURCE_ID . '}/card-from-uid/{uid}',
				'CardController@viewFromUid'
			)
				->parameters()->path(self::PARENT_RESOURCE_ID)->string()->required()
				->parameters()->path('uid')->string()->required()
				->parameters()->query('markClientDate')->bool()->describe('If set, remove all pending transactions after retrieving them.')
				->returns()->one(self::RESOURCE_DEFINITION);
		}

        $childResource->tag('cards');

		return $childResource;
    }

    /**
     * @param Request $request
     * @param $organisationId
     * @param $cardUid
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function viewFromUid(Request $request, $organisationId, $cardUid)
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);

        $card = Card::getFromUid($organisation, $cardUid);

        $this->authorizeView($request, $card);

        $context = $this->getContext(Action::VIEW);
        $resource = $this->toResource($card, $context);

        // if $markClientDate, this will effectively remove all pending transactions.
        $markClientDate = $request->query('markSynced');
        if ($markClientDate) {
            foreach ($card->getPendingTransactions() as $transaction) {
                /** @var Transaction $transaction */
                $transaction->has_synced = true;
                $transaction->save();
            }
        }

        return new ResourceResponse($resource);
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        return $event->cards();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $organisationId = $request->route(self::PARENT_RESOURCE_ID);
        return Organisation::findOrFail($organisationId);
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
     * @throws ResourceException
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);
        return $entity;
    }
}
