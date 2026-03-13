<?php

namespace App\Http\DeviceApi\V1\Controllers;

use CatLab\Charon\Collections\RouteCollection;

class TableController extends \App\Http\Shared\V1\Controllers\TableController
{
    public static function setRoutes(RouteCollection $routes, $only = [
        'index', 'view', 'store', 'edit'
    ]) {
        parent::setRoutes($routes, $only);
    }
}
