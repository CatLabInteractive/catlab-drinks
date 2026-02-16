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

namespace App\Http\ManagementApi\V1\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Factories\OrderEntityFactory;
use App\Http\ManagementApi\V1\ResourceDefinitions\MenuItemResourceDefinition;
use App\Http\Shared\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Card;
use App\Models\Event;
use App\Models\Order;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Exceptions\InvalidEntityException;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use App\Http\Shared\V1\Controllers\Base\ResourceController;

/**
 * Class PublicController
 *
 * This controller is behind a special authentication middleware that
 * injects an event in the request.
 *
 * @package App\Http\ManagementApi\V1\Controllers
 */
class PublicController extends ResourceController
{
    public function __construct()
    {
        parent::__construct(MenuItemResourceDefinition::class);
    }

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->group(
            [
                'tags' => 'public'
            ],
            function(RouteCollection $routes) {

                $routes->get('public/menu', 'PublicController@menu')
                    ->returns()->many(MenuItemResourceDefinition::class);

                $routes->post('public/order', 'PublicController@order')
                    ->parameters()->resource(OrderResourceDefinition::class)->one()
                    ->returns()->many(MenuItemResourceDefinition::class);

            }
        );
    }

    /**
     * Get all available menu items.
     * @return ResourceResponse
     * @throws InvalidContextAction
     * @throws InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function menu()
    {
        /** @var Event $event */
        $event = \Request::input('event');

        // Check if the bar is actually open.
        if (!$event->isOpen()) {
            return new JsonResponse([
                'error' => [
                    'message' => 'De bar aanvaardt op dit moment geen bestellingen. Bestel je drankje aan de bar of wacht tot de pauze voorbij is.'
                ]
            ], 423);
        }

        $card = $this->getCard($event);
        if (!$card && !$event->allow_unpaid_online_orders) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Het is niet mogelijk te bestellen zonder een gekoppelde kaart. Contacteer een medewerker.'
                ]
            ], 423);
        }

        $context = $this->getContext(Action::VIEW);

        /*
        $menuItems = $this->getModels($event->menuItems()->where('is_selling', '=', true), $context)->getModels();

        $resources = $this->toResources($menuItems, $context, MenuItemResourceDefinition::class);
        */

        $resources = $this->getResources(
            $event->menuItems()->where('is_selling', '=', true),
            $context,
            MenuItemResourceDefinition::class
        );
        return new ResourceResponse($resources, $context);
    }

    /**
     *
     * @return ResourceResponse|JsonResponse
     * @throws InvalidContextAction
     * @throws InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\NoInputDataFound
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function order()
    {
        /** @var Event $event */
        $event = \Request::input('event');

        // Check if the bar is actually open.
        if (!$event->isOpen()) {
            return new JsonResponse([
                'error' => [
                    'message' => 'De bar aanvaardt momenteel geen bestellingen. Probeer het later nog eens.'
                ]
            ], 423);
        }

        // Process the order
        $context = $this->getContext(Action::CREATE);

        $bodyResource = $this->bodyToResource($context, OrderResourceDefinition::class);

        /** @var Order $entity */
        $entity = $this->toEntity(
            $bodyResource,
            $context,
            null,
            OrderResourceDefinition::class,
            new OrderEntityFactory($event)
        );

        $entity->uid = Uuid::uuid1();
        $entity->paid = false;

        // Lock the price of the order items down.
        foreach ($entity->order as $orderItem) {
            $orderItem->price = $orderItem->menuItem->price;
        }

        // Split the order by categories
        if ($event->split_orders_by_categories) {
            $newOrders = $entity->splitByCategories();
        } else {
            $newOrders = [ $entity ];
        }

        // From now on we shoudln't use the entity anymore, as it is not correct.
        $entity = null;

        // Do we have a card token, so we can pay immediately?
        $card = $this->getCard($event);
        if ($card) {

            // First make sure we can afford the whole order
            if (!$card->canAfford($newOrders)) {
                return new JsonResponse([
                    'error' => [
                        'message' => 'Het saldo op je kaart is ontoereikend. Herlaad je kaart door de QR code te scannen, of ga naar de kassa.'
                    ]
                ], 402);
            }

            // Go through the orders and spend the money.
            foreach ($newOrders as $entity) {
                try {
                    $card->spend($entity);
                    $entity->paid = true;
                    $entity->payment_type = 'nfc-card-online';

                    // update the items in the order to make sure they have the correct price.
                    foreach ($entity->order as $orderItem) {
                        $orderItem->price *= $entity->getDiscountFactor();
                    }

                } catch (InsufficientFundsException $e) {
                    // This should really never be called.
                    return new JsonResponse([
                        'error' => [
                            'message' => 'Het saldo op je kaart is ontoereikend. Herlaad je kaart door de QR code te scannen, of ga naar de kassa.'
                        ]
                    ], 402);
                }
            }
        } elseif (!$event->allow_unpaid_online_orders) {

            // Set all orders to unpaid.
            foreach ($newOrders as $entity) {
                $entity->paid = false;
            }

            return new JsonResponse([
                'error' => [
                    'message' => 'Het is niet mogelijk te bestellen zonder een gekoppelde kaart. Contacteer een medewerker.'
                ]
            ], 402);
        }

        foreach ($newOrders as $entity) {

            $entity->event()->associate($event);
            $entity->status = Order::STATUS_PENDING;

            $entity->saveRecursively();

        }

        $readContext = $this->getContext(Action::VIEW);
        $resource = $this->toResources($newOrders, $readContext, OrderResourceDefinition::class);

        return new ResourceResponse($resource, $readContext);
    }

    /**
     * @param Event $event
     * @return Card|null
     */
    protected function getCard(Event $event) {
        $cardToken = \Request::header('X-Card-Token');
        if (!$cardToken) {
            return null;
        }

        return Card::getFromOrderTokenOrAlias($event->organisation, $cardToken);
    }
}
