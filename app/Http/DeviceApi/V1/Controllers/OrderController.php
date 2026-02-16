<?php

namespace App\Http\DeviceApi\V1\Controllers;

use App\Models\Order;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends \App\Http\Shared\V1\Controllers\OrderController
{
    public static function setRoutes(RouteCollection $routes, array $only = [
        'index', 'view', 'edit', 'destroy'
    ]) {
        $childResource = parent::setRoutes($routes, $only);

        $parentPath = 'events/{' . self::PARENT_RESOURCE_ID . '}/orders';

        $childResource->post($parentPath, 'OrderController@store')
            ->summary(function () {
                $entityName = ResourceDefinitionLibrary::make(static::RESOURCE_DEFINITION)
                    ->getEntityName(false);

                return 'Create a new ' . $entityName;
            })
            ->parameters()->resource(static::RESOURCE_DEFINITION)->many()->required()
            ->parameters()->path(self::PARENT_RESOURCE_ID)->string()->required()
            ->returns()->statusCode(200)->many(static::RESOURCE_DEFINITION);
    }

    /**
     * Create a new entity
     * @param Request $request
     * @return Response
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\NoInputDataFound
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function store(Request $request)
    {
        $this->request = $request;

        $this->authorizeCreate($request);

        $writeContext = $this->getContext(Action::CREATE);
        $readContext = $this->getContext(Action::INDEX);

        $inputResources = $this->bodyToResources($writeContext);
        $resources = $this->getResourceTransformer()->getResourceFactory()->createResourceCollection();

        foreach ($inputResources as $inputResource) {

            try {
                $inputResource->validate($writeContext);
            } catch (ResourceValidationException $e) {
                return $this->getValidationErrorResponse($e);
            }

            $entity = $this->toEntity($inputResource, $writeContext);

            // Look for unique identifier duplicate
            $existing = Order::where('uid', '=', $entity->uid)->first();
            if ($existing) {
                $resources[] = $this->toResource($existing, $readContext);
                continue;
            }

            // Save the entity
            $this->saveEntity($request, $entity);

            $resources[] = $this->toResource($entity, $readContext);
        }

        // Turn back into a resource
        return $this->getResourceResponse($resources, $readContext);
    }
}
