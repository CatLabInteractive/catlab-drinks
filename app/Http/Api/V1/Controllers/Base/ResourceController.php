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

namespace App\Http\Api\V1\Controllers\Base;

use App\Http\Controllers\Controller;
use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Laravel\InputParsers\JsonBodyInputParser;
use CatLab\CursorPagination\CursorPaginationBuilder;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Laravel\Processors\PaginationProcessor;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use Illuminate\Database\Eloquent\Model;

use Request;
use Response;

/**
 * Class ResourceController
 * @package App
 */
class ResourceController extends Controller
{
    use \CatLab\Charon\Laravel\Controllers\ResourceController;

    /**
     * AbstractResourceController constructor.
     * @param string $resourceDefinitionClass
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function __construct($resourceDefinitionClass)
    {
        $this->setResourceDefinition(
            ResourceDefinitionLibrary::make($resourceDefinitionClass)
        );
    }

    /**
     * @param string $ability
     * @param array $arguments
     * @return \Illuminate\Auth\Access\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        $arguments = func_get_args();
        array_shift($arguments);

        // @todo this is still not correct

        // No object defined?
        if (is_array($arguments) && count($arguments) === 0) {
            $arguments[] = $this->resourceDefinition->getEntityClassName();
        }

        return parent::authorize($ability, $arguments);
    }

    /**
     * @param int $id
     * @param string $resource
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFound($id, $resource)
    {
        if ($resource) {
            return $this->error('Resource ' . $id . ' ' . $resource . ' not found.');
        } else {
            return $this->error('Resource ' . $id . ' not found.');
        }
    }

    /**
     * @param $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error($message)
    {
        return Response::json($this->getErrorMessage($message));
    }

    /**
     * @param string $message
     * @return array
     */
    protected function getErrorMessage($message)
    {
        return ['error' => ['message' => $message]];
    }

    /**
     * Output a resource or a collection of resources
     *
     * @param $models
     * @param array $parameters
     * @param null $resourceDefinition
     * @return \Illuminate\Http\JsonResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\NotImplementedException
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function outputList($models, array $parameters = [], $resourceDefinition = null)
    {
        $resourceDefinition = $resourceDefinition ?? $this->resourceDefinition;

        $context = $this->getContext(Action::INDEX, $parameters);

        $models = $this->filterAndGet(
            $models,
            $resourceDefinition,
            $context,
            Request::input('records', 10)
        );

        $output = $this->modelsToResources($models, $context, $resourceDefinition);
        return Response::json($output);
    }

    /**
     * Output a resource or a collection of resources
     *
     * @param $models
     * @param array $parameters
     * @return \Illuminate\Http\JsonResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function output($models, array $parameters = [])
    {
        if (ArrayHelper::isIterable($models)) {
            $context = $this->getContext(Action::INDEX, $parameters);
        } else {
            $context = $this->getContext(Action::VIEW, $parameters);
        }

        $output = $this->modelsToResources($models, $context);
        return Response::json($output);
    }

    /**
     * @param Model|Model[] $models
     * @param Context $context
     * @param null $resourceDefinition
     * @return array|\mixed[]
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    protected function modelsToResources($models, Context $context, $resourceDefinition = null)
    {
        if (ArrayHelper::isIterable($models)) {
            return $this->toResources($models, $context, $resourceDefinition)->toArray();
        } elseif ($models instanceof Model) {
            return $this->toResource($models, $context, $resourceDefinition)->toArray();
        } else {
            return $models;
        }
    }

    /**
     * @param string $action
     * @param array $parameters
     * @return Context|string
     */
    protected function getContext($action = Action::VIEW, $parameters = []): \CatLab\Charon\Interfaces\Context
    {
        $context = new Context($action, $parameters);

        if ($toShow = Request::input('fields')) {
            $context->showFields(array_map('trim', $toShow));
        }

        if ($toExpand = Request::input('expand')) {
            $context->expandFields(array_map('trim', $toExpand));
        }

        $context->addProcessor(new PaginationProcessor(CursorPaginationBuilder::class));

        $context->setUrl(Request::url());

        $context->addInputParser(JsonBodyInputParser::class);

        return $context;
    }

    /**
     * @param ResourceValidationException $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getValidationErrorResponse(ResourceValidationException $e)
    {
        return Response::json([
            'error' => [
                'status' => 422,
                'message' => 'Could not decode resource.',
                'issues' => $e->getMessages()->toMap()
            ]
        ])->setStatusCode(422);
    }
}
