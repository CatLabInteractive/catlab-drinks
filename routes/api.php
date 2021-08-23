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
$routeCollection = include __DIR__ . '/../app/Http/Api/V1/routes.php';
$routeTransformer->transform($routeCollection);

// Notification endpoint for topups.
Route::post( '/topup/{cardId}/{orderId}/notification', 'App\Http\Controllers\TopupController@notification');
Route::get( '/topup/{cardId}/{orderId}/notification', 'App\Http\Controllers\TopupController@notification');
