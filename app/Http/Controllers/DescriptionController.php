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

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Laravel\InputParsers\JsonBodyInputParser;
use CatLab\Charon\OpenApi\V2\OpenApiV2Builder;
use CatLab\Charon\OpenApi\V3\OpenApiV3Builder;
use CatLab\Charon\Swagger\Authentication\OAuth2Authentication;
use CatLab\Charon\Swagger\SwaggerBuilder;
use Laravel\Passport\Passport;

/**
 * Class DescriptionController
 * @package App\Http\ManagementApi\V1\Controllers
 */
class DescriptionController extends Controller
{
	use \CatLab\Charon\Laravel\Controllers\ResourceController;

	/**
	 * @return RouteCollection
	 */
	public function getRouteCollections() : array
	{
		$management = include __DIR__ . '/../ManagementApi/V1/routes.php';
		$device = include __DIR__ . '/../DeviceApi/V1/routes.php';

		return [
			$management,
			$device
		];
	}

	/**
	 * @param $format
	 * @return \Illuminate\Http\Response
	 * @throws \CatLab\Charon\Exceptions\RouteAlreadyDefined
	 */
	public function description()
	{
		switch ($this->getRequest()->route('format')) {
			case 'txt':
			case 'text':
				return $this->textResponse();
				break;

			case 'json':
			default:
				return $this->swaggerResponse();
				break;
		}
	}

	/**
	 * @return \Illuminate\Http\Response
	 */
	protected function textResponse()
	{
		$routes = $this->getRouteCollections();

		$output = '';
		foreach ($routes as $route) {
			$output .= $route->__toString();
		}

		return \Response::make($output, 200, [ 'Content-type' => 'text/text' ]);
	}

	/**
	 * @return mixed
	 * @throws \CatLab\Charon\Exceptions\RouteAlreadyDefined
	 * @throws \CatLab\Charon\Exceptions\InvalidScalarException
	 */
	protected function swaggerResponse()
	{
		$builder = new OpenApiV2Builder(\Request::getHttpHost(), '/');

		$builder
			->setTitle(config('charon.title'))
			->setDescription(config('charon.description'))
			->setContact(
				config('charon.contact.name'),
				config('charon.contact.url'),
				config('charon.contact.email')
			)
			->setVersion('1.0');

		foreach ($this->getRouteCollections() as $collection) {
			foreach ($collection->getRoutes() as $route) {
				$builder->addRoute($route);
			}
		}

		// Authentication
		/*
		$oauth = new OAuth2Authentication('oauth2');
		$oauth->setAuthorizationUrl(url('oauth/authorize'))
			->setFlow('implicit');

		// Add all scopes
		foreach (Passport::scopes() as $scope) {
			$oauth->addScope($scope->id, $scope->description);
		}

		$builder->addAuthentication($oauth);*/

		return $builder->build($this->getContext());
	}

	/**
	 * Set the input parsers that will be used to turn requests into resources.
	 * @param \CatLab\Charon\Models\Context $context
	 */
	protected function setInputParsers(\CatLab\Charon\Models\Context $context)
	{
		$context->addInputParser(JsonBodyInputParser::class);

		// Don't include PostInputParser.
		// $context->addInputParser(PostInputParser::class);
	}
}
