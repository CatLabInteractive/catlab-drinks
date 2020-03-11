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

use App\Http\Api\V1\ResourceDefinitions\FinancialOverviewResourceDefinition;
use App\Models\FinancialOverview;
use App\Models\Organisation;
use App\Models\Transaction;
use Carbon\Carbon;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;

/**
 * Class FinancialOverviewController
 * @package App\Http\Api\V1\Controllers
 */
class FinancialOverviewController extends Base\ResourceController
{
    const RESOURCE_DEFINITION = FinancialOverviewResourceDefinition::class;
    const PARENT_RESOURCE_ID = 'organisationId';

    /**
     * FinancialOverviewController constructor.
     */
    public function __construct()
    {
        parent::__construct(self::RESOURCE_DEFINITION);
    }

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->group([], function(RouteCollection $routes) {
            $routes->get(
                'organisations/{' . self::PARENT_RESOURCE_ID . '}/financial-overview',
                'FinancialOverviewController@overview'
            )
                ->parameters()->path(self::PARENT_RESOURCE_ID)->string()->required()
                ->returns()->one(self::RESOURCE_DEFINITION);

            $routes->tag('financial');
        });
    }

    /**
     * @param $organisationId
     * @return ResourceResponse
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     * @throws \CatLab\Charon\Exceptions\InvalidEntityException
     * @throws \CatLab\Charon\Exceptions\InvalidPropertyException
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\IterableExpected
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function overview($organisationId)
    {
        $organisation = Organisation::findOrFail($organisationId);
        $this->authorize('financialOverview', $organisation);

        $overview = $this->getFinancialOverview($organisation);

        $context = $this->getContext(Action::VIEW);
        $resource = $this->toResource($overview, $context);

        return new ResourceResponse($resource);
    }

    /**
     * @param Organisation $organisation
     * @return FinancialOverview
     */
    private function getFinancialOverview(Organisation $organisation)
    {
        $out = new FinancialOverview();

        $totalCredit = Transaction::query()
            ->leftJoin('cards', 'cards.id', '=', 'card_transactions.card_id')
            ->where('cards.organisation_id', '=', $organisation->id)
            ->sum('value');

        $out->totalCardCredit = $totalCredit;

        $this->setTotalTopup($out, $organisation);

        return $out;
    }

    /**
     * @param FinancialOverview $overview
     * @param Organisation $organisation
     * @param \DateTime $since
     * @return void
     */
    private function setTotalTopup(
        FinancialOverview $overview,
        Organisation $organisation
    ) {
        $since = Carbon::now()->subDay();

        $topups = Transaction::query()
            ->leftJoin('cards', 'cards.id', '=', 'card_transactions.card_id')
            ->where('cards.organisation_id', '=', $organisation->id)
            ->where('card_transactions.value', '>', 0)
            ->where('card_transactions.created_at', '>', $since)
            ->sum('value');

        $overview->topups24Hours = $topups;
    }
}
