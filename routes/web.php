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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', 'DocumentController@swagger');
Route::get('/docs/oauth2', 'DocumentController@oauth2Redirect');

// Link to the single page web application
Route::get('/client/{any?}', 'ClientController@index')
    ->where('any', '.*')
    ->middleware('auth')
;

Route::get('/{orderController}', 'OrderController@view');

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
