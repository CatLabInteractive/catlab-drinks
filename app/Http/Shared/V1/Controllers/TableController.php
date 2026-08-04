<?php

namespace App\Http\Shared\V1\Controllers;

use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Http\Shared\V1\ResourceDefinitions\TableResourceDefinition;
use App\Models\Event;
use App\Models\Table;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class TableController
 * @package App\Http\Shared\V1\Controllers
 */
abstract class TableController extends ResourceController
{
    const RESOURCE_DEFINITION = TableResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'event';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     * @param string[] $only
     * @return RouteCollection
     * @throws InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes, $only = [
        'index', 'view', 'store', 'edit', 'destroy'
    ]) {
        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            'events/{parentId}/tables',
            'tables',
            'TableController',
            [
                'id' => self::RESOURCE_ID,
                'only' => $only,
            ]
        );

        $childResource->tag('tables');

        // Bulk generate endpoint
        $childResource->post('events/{parentId}/tables/generate', 'TableController@bulkGenerate')
            ->summary('Bulk generate tables')
            ->parameters()->path('parentId')->string()->required()
            ->returns()->statusCode(200)->many(static::RESOURCE_DEFINITION);

        return $childResource;
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        return $event->tables();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $eventId = $request->route('parentId');
        return Event::findOrFail($eventId);
    }

    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Bulk generate tables for an event.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function bulkGenerate(Request $request)
    {
        $event = $this->getParent($request);
        $this->authorizeCreate($request);

        $count = max(1, min(100, intval($request->input('count', 1))));
        $tables = Table::bulkGenerate($event, $count);

        $readContext = $this->getContext(Action::INDEX);
        $resources = $this->getResourceTransformer()->getResourceFactory()->createResourceCollection();
        foreach ($tables as $table) {
            $resources[] = $this->toResource($table, $readContext);
        }

        return $this->getResourceResponse($resources, $readContext);
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
