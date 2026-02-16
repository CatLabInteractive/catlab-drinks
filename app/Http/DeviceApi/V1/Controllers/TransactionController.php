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
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Models\Card;
use App\Models\Organisation;
use App\Models\Transaction;
use App\Tools\TransactionMerger;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Exceptions\InvalidEntityException;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Exceptions\InvalidResourceDefinition;
use CatLab\Charon\Exceptions\InvalidTransformer;
use CatLab\Charon\Exceptions\IterableExpected;
use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class TransactionController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class TransactionController extends ResourceController
{
    const RESOURCE_DEFINITION = TransactionResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'card';

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
            'cards/{' . self::PARENT_RESOURCE_ID . '}/transactions',
            'transactions',
            'TransactionController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID,
                'only' => [
                    'index', 'view', 'edit'
                ]
            ]
        );

        $childResource->post('organisations/{organisationId}/merge-transactions', 'TransactionController@mergeTransactions')
            ->summary('Merges offline stored transactions')
            ->parameters()->path('organisationId')->required()
            ->parameters()->resource(TransactionResourceDefinition::class)->many();


        $childResource->get('organisations/{organisationId}/transactions', 'TransactionController@getFromOrganisation')
            ->summary('Return all transactions happening in the organisation')
            ->parameters()->path('organisationId')->required()
            ->parameters()->resource(TransactionResourceDefinition::class)->many();

        $childResource->tag('transactions');
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
	 * @param Request $request
	 * @param $organisationId
	 * @return ResourceResponse
	 * @throws InvalidContextAction
	 * @throws InvalidEntityException
	 * @throws InvalidPropertyException
	 * @throws InvalidResourceDefinition
	 * @throws InvalidTransformer
	 * @throws IterableExpected
	 * @throws NoInputDataFound
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

    /**
     * @param $organisationId
     * @return \CatLab\Charon\Laravel\Contracts\Response
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function getFromOrganisation($organisationId)
    {
        $organisation = Organisation::findOrFail($organisationId);

        $this->authorizeCrudRequest('organisationIndex', null, $organisation);

        $context = $this->getContext(Action::INDEX);

        // First load the filters so that we can use these in the policy
        $resourceDefinition = $resourceDefinition ?? $this->resourceDefinition;
        $filters = $this->resourceTransformer->getFilters(
            $this->getRequest()->query(),
            $resourceDefinition,
            $context
        );

        $filteredModels = $this->getFilteredModels($organisation->transactions(), $context, $filters, $resourceDefinition);
        $resources = $this->toResources(
            $filteredModels->getModels(),
            $context,
            $resourceDefinition,
            $filteredModels->getFilterResults()
        );

        return $this->getResourceResponse($resources, $context);
    }

    /**
     * Called before saveEntity
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param $isNew
     * @return Model
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);
        return $entity;
    }
}
