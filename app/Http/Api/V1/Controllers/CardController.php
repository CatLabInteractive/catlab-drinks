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

use App\Exceptions\TransactionCountException;
use App\Http\Api\V1\ResourceDefinitions\CardDataResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\CardResourceDefinition;
use App\Models\Card;
use App\Models\CardData;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Transaction;
use App\Tools\CardDataMerger;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
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
     * @param $cardId
     * @return ResourceResponse|JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Throwable
     */
    public function updateCardData(Request $request, $cardId)
    {
        $context = $this->getContext(Action::CREATE);

        /** @var CardData $cardData */
        $cardDataResource = $this->bodyToResource($context, CardDataResourceDefinition::class);
        $cardData = $this->toEntity($cardDataResource, $context, new CardData());

        /** @var Card $card */
        $card = Card::findOrFail($cardId);
        $this->authorizeEdit($request, $card);

        try {
            $merger = new CardDataMerger($card);
            $merger->merge($cardData);
        } catch (TransactionCountException $e) {
            \Log::error('Transaction count is lower than our own transaction count: ' . print_r($cardData));
            return new JsonResponse([
                'error' => [
                    'message' => 'Transaction count is lower than our own transaction count.'
                ]
            ], 402);
        }


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

        $card->transaction_count = 0;
        $card->save();

        $card
            ->transactions()
            ->where('card_sync_id', '>=', 0)
            ->update([
                'card_sync_id' => null,
                'client_date' => null,
                'has_synced' => false
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
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);
    }
}
