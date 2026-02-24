<?php

namespace App\Http\DeviceApi\V1\Controllers;

use App\Http\DeviceApi\V1\ResourceDefinitions\AttendeeResourceDefinition;
use CatLab\Charon\Collections\RouteCollection;

class AttendeeController extends \App\Http\Shared\V1\Controllers\AttendeeController
{
    const RESOURCE_DEFINITION = AttendeeResourceDefinition::class;

    public static function setRoutes(RouteCollection $routes, array $only = [
        'index'
    ]): RouteCollection
    {
        return parent::setRoutes($routes, $only);
    }
}
