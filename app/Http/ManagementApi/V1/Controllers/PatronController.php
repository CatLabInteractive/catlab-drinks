<?php

namespace App\Http\ManagementApi\V1\Controllers;

use CatLab\Charon\Collections\RouteCollection;

class PatronController extends \App\Http\Shared\V1\Controllers\PatronController
{
    public static function setRoutes(RouteCollection $routes, $only = [
        'index', 'view', 'store', 'edit'
    ]) {
        parent::setRoutes($routes, $only);
    }
}
