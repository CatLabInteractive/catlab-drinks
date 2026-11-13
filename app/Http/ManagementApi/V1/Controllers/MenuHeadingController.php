<?php

namespace App\Http\ManagementApi\V1\Controllers;

use App\Models\Event;
use App\Models\MenuHeading;
use App\Services\SequenceWriter;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Http\Request;

class MenuHeadingController extends \App\Http\Shared\V1\Controllers\MenuHeadingController
{
	public static function setRoutes(RouteCollection $routes, $only = [
		'index', 'view', 'store', 'edit', 'destroy'
	]) {
		$childResource = parent::setRoutes($routes, $only);

		$childResource->put('events/{parentId}/headings/sequence', 'MenuHeadingController@sequence')
			->summary('Store the order in which the headings of an event are shown')
			->parameters()->post('items');

		return $childResource;
	}

	/**
	 * Store the heading sequence. Listed headings get position 1..k in list order,
	 * the other headings of the event follow in their current order.
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function sequence(Request $request)
	{
		/** @var Event $event */
		$event = $this->getParent($request);
		$this->authorize('create', [ MenuHeading::class, $event ]);

		app(SequenceWriter::class)->write($event->menuHeadings(), $request->json('items'));

		return \Response::json([ 'success' => true ]);
	}
}
