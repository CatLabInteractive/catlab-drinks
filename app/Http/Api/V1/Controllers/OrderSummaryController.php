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

use App\Http\Api\V1\ResourceDefinitions\EventResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\OrderSummaryResourceDefinition;
use App\Http\Api\V1\Transformers\DateTransformer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSummary;
use App\Models\OrderSummaryItem;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class EventController
 * @package App\Http\Api\V1\Controllers
 */
class OrderSummaryController extends Base\ResourceController
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
            //->parameters()->query('startDate')->datetime(DateTransformer::class)
            //->parameters()->query('endDate')->datetime(DateTransformer::class)
            ->tag('orders');
    }

    /**
     * OrderSummaryController constructor.
     */
    public function __construct()
    {
        parent::__construct(OrderSummaryResourceDefinition::class);
    }

    /**
     * @param Event $event
     * @return ResourceResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function getSummary(Event $event)
    {
        $this->authorize('orderSummary', $event);

        $orderSummary = new OrderSummary();

        $context = $this->getContext(Action::VIEW);

        // Build up the query
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '=', Order::STATUS_PROCESSED)
            ->where('orders.event_id', '=', $event->id)
            ->selectRaw('sum(order_items.amount) as sales_items')
            ->selectRaw('sum(order_items.amount * order_items.price) as sales_total')
            ->selectRaw('min(orders.created_at) as first_sale_date')
            ->selectRaw('max(orders.created_at) as last_sale_date')
        ;

        $groupedQuery = clone $query;
        $groupedQuery->selectRaw('order_items.menu_item_id');
        $groupedQuery->groupBy('order_items.menu_item_id');
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

            $orderSummary->items[] = $orderItem;
        }

        $resource = $this->toResource($orderSummary, $context, OrderSummaryResourceDefinition::class);
        return new ResourceResponse($resource);
    }
}
