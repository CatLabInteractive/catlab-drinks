<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'HomeController@welcome');

Route::get('/docs', 'DocumentController@swagger');
Route::get('/docs/oauth2', 'DocumentController@oauth2Redirect');

/*
 * Link to the single page web application
 */
Route::get('/sales/{any?}', 'ClientController@index')
    ->where('any', '.*')
    ->middleware('auth');

/*
 * Order panel
 */
Route::get('/order/{orderId}/{any?}', 'OrderController@view')
    ->where('any', '.*');

Route::get('/topup/{cardId}', 'TopupController@topupForm');
Route::post('/topup/{cardId}', 'TopupController@processTopup');
Route::get('/topup/{cardId}/{orderId}', 'TopupController@status');

Route::get('/qr-generator', 'QrGenerator@generator');
Route::get('/qr-generator/code', 'QrGenerator@code');

// Do we have catlab client id? (my own personal single sign on service)
if (config('services.catlab.client_id')) {
    \CatLab\Accounts\Client\Controllers\LoginController::setRoutes();
} else {
    // Not set? Use default laravel authentication.
    Auth::routes();
}

Route::get('/home', 'HomeController@index')->name('home');
