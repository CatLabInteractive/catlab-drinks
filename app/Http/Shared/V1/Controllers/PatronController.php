<?php

namespace App\Http\Shared\V1\Controllers;

use App\Http\Shared\V1\Controllers\Base\ResourceController;
use App\Http\Shared\V1\ResourceDefinitions\PatronResourceDefinition;
use App\Models\Event;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Exceptions\InvalidContextAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class PatronController
 * @package App\Http\Shared\V1\Controllers
 */
abstract class PatronController extends ResourceController
{
    const RESOURCE_DEFINITION = PatronResourceDefinition::class;
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
        'index', 'view', 'store', 'edit'
    ]) {
        $childResource = $routes->childResource(
            static::RESOURCE_DEFINITION,
            'events/{parentId}/patrons',
            'patrons',
            'PatronController',
            [
                'id' => self::RESOURCE_ID,
                'only' => $only,
            ]
        );

        $childResource->tag('patrons');

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
        return $event->patrons();
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
