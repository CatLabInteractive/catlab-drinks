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
    ->middleware('auth')
;

/*
 * Order panel
 */
Route::get('/order/{orderId}/{any?}', 'OrderController@view')
    ->where('any', '.*');

//Auth::routes();
\CatLab\Accounts\Client\Controllers\LoginController::setRoutes();

Route::get('/home', 'HomeController@index')->name('home');
