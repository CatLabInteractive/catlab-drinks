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

use App\Http\DeviceApi\V1\ResourceDefinitions\TransactionResourceDefinition;
use App\Models\Organisation;
use App\Models\Transaction;
use App\Tools\TransactionMerger;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Http\Request;

/**
 * Class TransactionController
 *
 * POS-facing transaction controller. Extends the shared transaction controller
 * and adds merge-transactions and edit capabilities for device API.
 *
 * @package App\Http\DeviceApi\V1\Controllers
 */
class TransactionController extends \App\Http\Shared\V1\Controllers\TransactionController
{
    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $childResource = static::setSharedRoutes($routes, ['index', 'view', 'edit']);

        $childResource->post('organisations/{organisationId}/merge-transactions', 'TransactionController@mergeTransactions')
            ->summary('Merges offline stored transactions')
            ->parameters()->path('organisationId')->required()
            ->parameters()->resource(TransactionResourceDefinition::class)->many();
    }

	/**
	 * @param Request $request
	 * @param $organisationId
	 * @return ResourceResponse
	 * @throws \Throwable
	 */
    public function mergeTransactions(Request $request, $organisationId)
	{
        /** @var Organisation $organisation */
        $organisation = Organisation::findOrFail($organisationId);
        $this->authorize('mergeTransactions', $organisation);

        $writeContext = $this->getContext(Action::CREATE);
        $resources = $this->bodyToResources($writeContext, TransactionResourceDefinition::class);

        $transactionMerger = new TransactionMerger($organisation);

        $entities = [];
        foreach ($resources as $resource) {
            /** @var Transaction $entity */
            $entity = $this->toEntity($resource, $writeContext);
            $entities[] = $entity;
        }

        $transactions = $transactionMerger->mergeTransactions($entities);

        $context = $this->getContext(Action::INDEX);
        $context->expandField('card');
        $context->showField('*');
        $context->showField('card');
        $resources = $this->toResources($transactions, $context);

        return new ResourceResponse($resources, $context);
    }
}
