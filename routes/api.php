<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
 * Convert Charon routes to Laravel routes.
 */
$routeTransformer = new \CatLab\Charon\Laravel\Transformers\RouteTransformer();

/** @var \CatLab\Charon\Collections\RouteCollection $routeCollection */
$routeCollection1 = include __DIR__ . '/../app/Http/ManagementApi/V1/routes.php';
$routeTransformer->transform($routeCollection1);

/** @var \CatLab\Charon\Collections\RouteCollection $routeCollection */
$routeCollection2 = include __DIR__ . '/../app/Http/DeviceApi/V1/routes.php';
$routeTransformer->transform($routeCollection2);

// Swagger documentation
Route::get('/api/v1/description.json', 'App\Http\Controllers\DescriptionController@description');

// Notification endpoint for topups.
Route::post( '/topup/{cardId}/{orderId}/notification', 'App\Http\Controllers\TopupController@notification');
Route::get( '/topup/{cardId}/{orderId}/notification', 'App\Http\Controllers\TopupController@notification');
