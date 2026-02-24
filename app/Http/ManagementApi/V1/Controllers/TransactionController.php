<?php

namespace App\Http\ManagementApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\TransactionResourceDefinition;
use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Models\Card;
use App\Models\Organisation;
use App\Models\Transaction;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
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
					'index', 'view'
				]
			]
		);

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
	 * @param $organisationId
	 * @return \CatLab\Charon\Laravel\Contracts\Response
	 */
	public function getFromOrganisation($organisationId)
	{
		$organisation = Organisation::findOrFail($organisationId);

		$this->authorizeCrudRequest('organisationIndex', null, $organisation);

		$context = $this->getContext(Action::INDEX);

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
