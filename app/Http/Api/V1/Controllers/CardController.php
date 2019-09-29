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

use App\Http\Api\V1\ResourceDefinitions\CardDataResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\CardResourceDefinition;
use App\Models\Card;
use App\Models\CardData;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Transaction;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class CardController
 * @package App\Http\Api\V1\Controllers
 */
class CardController extends Base\ResourceController
{
    const RESOURCE_DEFINITION = CardResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'organisationId';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
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

        $childResource->get(
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/card-from-uid/{uid}',
            'CardController@viewFromUid'
        )
            ->parameters()->path(self::PARENT_RESOURCE_ID)->string()->required()
            ->parameters()->path('uid')->string()->required()
            ->parameters()->query('markClientDate')->bool()->describe('If set, remove all pending transactions after retrieving them.')
            ->returns()->one(self::RESOURCE_DEFINITION);

        $childResource->post(
            'cards/{' . self::RESOURCE_ID . '}/card-data',
            'CardController@updateCardData'
        )
            ->parameters()->path(self::RESOURCE_ID)->string()->required()
            ->parameters()->resource(CardDataResourceDefinition::class)
            ->returns()->one(self::RESOURCE_DEFINITION);

        $childResource->post(
            'cards/{' . self::RESOURCE_ID . '}/reset-transactions',
            'CardController@resetTransactions'
        )
            ->parameters()->path(self::RESOURCE_ID)->string()->required()
            ->returns()->one(self::RESOURCE_DEFINITION)
            ->summary('Set all transactions on this card to \'pending\'.');

        $childResource->tag('cards');
    }

    /**
     * @param Request $request
     * @param $organisationId
     * @param $cardUid
     * @return ResourceResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function viewFromUid(Request $request, $organisationId, $cardUid)
    {
        $organisation = $this->getParent($request);

        // Look fo card
        $card = $organisation->cards()->where('uid', '=', $cardUid)->first();
        if (!$card) {
            $card = new Card();
            $card->uid = $cardUid;
            $card->transaction_count = 0;
            $card->balance = 0;
            $card->organisation()->associate($organisation);

            $this->authorizeCreate($request);
            $card->save();
        }

        $this->authorizeView($request, $card);

        $context = $this->getContext(Action::VIEW);
        $resource = $this->toResource($card, $context);

        // if $markClientDate, this will effectively remove all pending transactions.
        $markClientDate = $request->query('markClientDate');
        if ($markClientDate) {
            foreach ($card->getPendingTransactions() as $transaction) {
                /** @var Transaction $transaction */
                $transaction->client_date = new \DateTime();
                $transaction->save();
            }
        }

        return new ResourceResponse($resource);
    }

    /**
     * @param Request $request
     * @param $cardId
     * @return ResourceResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateCardData(Request $request, $cardId)
    {
        /** @var Card $card */
        $card = Card::findOrFail($cardId);
        $this->authorizeEdit($request, $card);

        $context = $this->getContext(Action::CREATE);

        /** @var CardData $cardData */
        $cardDataResource = $this->bodyToResource($context, CardDataResourceDefinition::class);
        $cardData = $this->toEntity($cardDataResource, $context);

        // do magic.
        $card->transaction_count = $cardData->transactionCount;
        $card->balance = $cardData->balance;

        $card->save();

        $readContext = $this->getContext(Action::VIEW);
        return new ResourceResponse($this->toResource($card, $readContext));
    }

    /**
     * @param Request $request
     * @param $cardId
     * @return ResourceResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function resetTransactions(Request $request, $cardId)
    {
        /** @var Card $card */
        $card = Card::findOrFail($cardId);
        $this->authorizeEdit($request, $card);

        $card->transactions()->update([
            'card_sync_id' => null,
            'client_date' => null
        ]);

        $readContext = $this->getContext(Action::VIEW);
        return new ResourceResponse($this->toResource($card, $readContext));
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
     * @param \Illuminate\Database\Eloquent\Model $entity
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        $this->traitBeforeSaveEntity($request, $entity);
    }
}