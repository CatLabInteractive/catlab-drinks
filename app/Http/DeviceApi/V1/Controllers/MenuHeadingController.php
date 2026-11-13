<?php

namespace App\Http\DeviceApi\V1\Controllers;

use CatLab\Charon\Collections\RouteCollection;

class MenuHeadingController extends \App\Http\Shared\V1\Controllers\MenuHeadingController
{
	public static function setRoutes(RouteCollection $routes, $only = [
		'index', 'view'
	]) {
		parent::setRoutes($routes, $only);
	}
}
