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

namespace App\Http\DeviceApi\V1\Controllers;

use App\Exceptions\TransactionCountException;
use App\Http\DeviceApi\V1\ResourceDefinitions\CardDataResourceDefinition;
use App\Models\Card;
use App\Models\CardData;
use App\Tools\CardDataMerger;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class CardController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class CardController extends \App\Http\Shared\V1\Controllers\CardController

{
	/**
	 * @param RouteCollection $routes
	 * @param array $only
	 * @throws InvalidContextAction
	 */
    public static function setRoutes(RouteCollection $routes): void
	{
		$childResource = parent::setSharedRoutes($routes, [
			'index',
			'view',
			'store',
			'edit',
			'destroy'
		]);

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
			$signingDevice = \Auth::user() instanceof \App\Models\Device ? \Auth::user() : null;
			$merger = new CardDataMerger($card, $signingDevice);
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
	 * @throws \CatLab\Charon\Exceptions\InvalidContextAction
	 * @throws \CatLab\Charon\Exceptions\InvalidEntityException
	 * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
	 * @throws \CatLab\Charon\Exceptions\InvalidTransformer
	 * @throws \CatLab\Charon\Exceptions\IterableExpected
	 * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
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
}
