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

use App\Http\Shared\V1\ResourceDefinitions\OrderSummaryResourceDefinition;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSummary;
use App\Models\OrderSummaryItem;
use Carbon\Carbon;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use App\Http\Shared\V1\Controllers\Base\ResourceController;

/**
 * Class OrderSummaryController
 * @package App\Http\Shared\V1\Controllers
 */
class OrderSummaryController extends ResourceController
{
    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidScalarException
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->get(
                'events/{event}/ordersummary',
                'OrderSummaryController@getSummary'
            )
            ->parameters()->path('event')->required()
            ->tag('orders');

        $routes->get(
            'events/{event}/ordersummary/names',
            'OrderSummaryController@getNameSummary'
        )
            ->parameters()->path('event')->required()
            ->tag('orders');
    }

    /**
     * OrderSummaryController constructor.
     */
    public function __construct()
    {
        parent::__construct(OrderSummaryResourceDefinition::class);
    }

    protected function getTotalQuery(Event $event)
    {
        // Build up the query
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '=', Order::STATUS_PROCESSED)
            ->where('orders.event_id', '=', $event->id)
            ->selectRaw('sum(order_items.amount) as sales_items')
            ->selectRaw('sum(round(order_items.amount * order_items.price * 100) / 100) as sales_total')
            ->selectRaw('min(orders.created_at) as first_sale_date')
            ->selectRaw('max(orders.created_at) as last_sale_date')
        ;

        return $query;
    }

    /**
     * @param Event $event
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function getSummary(Event $event)
    {
        $this->authorize('orderSummary', $event);

        $orderSummary = new OrderSummary();

        $context = $this->getContext(Action::VIEW);

        $query = $this->getTotalQuery($event);

        $groupedQuery = clone $query;
        $groupedQuery->selectRaw('order_items.price');
        $groupedQuery->selectRaw('order_items.menu_item_id');
        $groupedQuery->groupBy('order_items.menu_item_id');
        $groupedQuery->groupBy('order_items.price');
        $groupedQuery->orderBy('sales_items', 'desc');
        $groupedQuery->orderBy('order_items.menu_item_id', 'asc');

        $result = $query->first();
        if (!$result) {
            abort(404);
        }

        $orderSummary->totalSales = floatval($result->sales_total);
        $orderSummary->amount = intval($result->sales_items);
        $orderSummary->startDate = Carbon::parse($result->first_sale_date);
        $orderSummary->endDate = Carbon::parse($result->last_sale_date);

        $items = $groupedQuery->get();
        foreach ($items as $item) {
            $orderItem = new OrderSummaryItem();

            $orderItem->menuItem = $item->menuItem;

            $orderItem->amount = intval($item->sales_items);
            $orderItem->totalSales = floatval($item->sales_total);
            $orderItem->startDate = Carbon::parse($item->first_sale_date);
            $orderItem->endDate = Carbon::parse($item->last_sale_date);
            $orderItem->price = floatval($item->price);

            if ($orderItem->menuItem && $orderItem->menuItem->vat_percentage > 0) {
                $orderItem->vat_percentage = $orderItem->menuItem->vat_percentage;
                $orderItem->net_total = round($orderItem->totalSales / (1 + $orderItem->vat_percentage / 100), 2);
                $orderItem->vat_total = $orderItem->totalSales - $orderItem->net_total;

                $orderSummary->totalSales = floatval($result->sales_total);

                $orderSummary->net_total += $orderItem->net_total;
                $orderSummary->vat_total += $orderItem->vat_total;
            }

            $orderSummary->items[] = $orderItem;
        }

        $resource = $this->toResource($orderSummary, $context, OrderSummaryResourceDefinition::class);
        return new ResourceResponse($resource);
    }

    /**
     * @param Event $event
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function getNameSummary(Event $event)
    {
        $this->authorize('orderSummary', $event);

        $orderSummary = new OrderSummary();

        $context = $this->getContext(Action::VIEW);

        $query = $this->getTotalQuery($event);

        $groupedQuery = clone $query;
        $groupedQuery->selectRaw('orders.requester');
        $groupedQuery->groupBy('orders.requester');
        //$groupedQuery->orderBy('sales_items', 'desc');
        $groupedQuery->orderByRaw('orders.requester is null desc');
        $groupedQuery->orderBy('orders.requester', 'asc');

        $result = $query->first();
        if (!$result) {
            abort(404);
        }

        $orderSummary->totalSales = floatval($result->sales_total);
        $orderSummary->amount = intval($result->sales_items);
        $orderSummary->startDate = Carbon::parse($result->first_sale_date);
        $orderSummary->endDate = Carbon::parse($result->last_sale_date);

        $items = $groupedQuery->get();
        foreach ($items as $item) {
            $orderItem = new OrderSummaryItem();

            $orderItem->amount = intval($item->sales_items);

            if ($item->requester) {
                $orderItem->name = $item->requester;
            } else {
                $orderItem->name = '';
            }

            $orderItem->totalSales = floatval($item->sales_total);
            $orderItem->startDate = Carbon::parse($item->first_sale_date);
            $orderItem->endDate = Carbon::parse($item->last_sale_date);
            $orderItem->price = floatval($item->price);

            $orderSummary->items[] = $orderItem;
        }

        $resource = $this->toResource($orderSummary, $context, OrderSummaryResourceDefinition::class);
        return new ResourceResponse($resource);
    }
}
