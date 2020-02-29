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

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\CardNameResourceDefinition;
use App\Models\Card;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Exceptions\InvalidEntityException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PublicCardNameController
 * @package App\Http\Api\V1\Controllers
 */
class PublicCardNameController extends ResourceController
{
    public function __construct()
    {
        parent::__construct(CardNameResourceDefinition::class);
    }

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->group(
            [
                'tags' => 'public'
            ],
            function(RouteCollection $routes) {

                $routes->get('public/card-name/{cardId}', 'PublicCardNameController@cardName')
                    ->returns()->one(CardNameResourceDefinition::class);

            }
        );
    }



    /**
     * @param $cardId
     * @return Response
     * @throws InvalidContextAction
     * @throws InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function cardName($cardId)
    {
        $card = Card::where('uid', '=', $cardId)->first();
        if (!$card) {
            $card = [];
        }

        $context = $this->getContext(Action::VIEW);

        $resource = $this->toResource($card, $context, CardNameResourceDefinition::class);

        return $this->toResponse($resource, $context);
    }
}
