<?php

namespace App\Http\Api\V1\Controllers\Base;

use App\Http\Controllers\Controller;
use CatLab\Base\Helpers\ArrayHelper;
use CatLab\CursorPagination\CursorPaginationBuilder;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Processors\PaginationProcessor;
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
     */
    public function __construct($resourceDefinitionClass)
    {
        $this->setResourceDefinition(ResourceDefinitionLibrary::make($resourceDefinitionClass));
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
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
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
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
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
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
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
    protected function getContext($action = Action::VIEW, $parameters = [])
    {
        $context = new Context($action, $parameters);

        if ($toShow = Request::input('fields')) {
            $context->showFields(array_map('trim', explode(',', $toShow)));
        }

        if ($toExpand = Request::input('expand')) {
            $context->expandFields(array_map('trim', explode(',', $toExpand)));
        }

        $context->addProcessor(new PaginationProcessor(CursorPaginationBuilder::class));

        $context->setUrl(Request::url());

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
                'message' => 'Could not decode resource.',
                'issues' => $e->getMessages()->toMap()
            ]
        ])->setStatusCode(400);
    }
}