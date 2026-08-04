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

Route::get('/', 'HomeController@welcome')->middleware('setup.redirect');
Route::view('/getting-started', 'getting-started');
Route::view('/student-union-cashless', 'landing.student-union-cashless');
Route::view('/indie-festival-payments', 'landing.indie-festival-payments');
Route::view('/hacker-camp-nfc', 'landing.hacker-camp-nfc');
Route::view('/whitepaper', 'whitepaper');

Route::get('/docs', 'DocumentController@swagger');
Route::get('/docs/oauth2', 'DocumentController@oauth2Redirect');

/*
 * License return handler (must be before the SPA catch-all)
 */
Route::get('/manage/devices/apply-license', 'LicenseController@applyLicense')
    ->middleware('auth');

/*
 * Deeplink to the CatLab accounts "my account" page (SSO users only).
 */
Route::get('/account', 'AccountLinkController@redirect')
    ->middleware('auth');

/*
 * Link to the single page web application
 */
Route::get('/manage/{any?}', 'ClientController@manage')
    ->where('any', '.*')
    ->middleware(['setup.redirect', 'auth']);

Route::get('/connect', 'ConnectController@show');

Route::get('/pos/{any?}', 'ClientController@pos')
    ->where('any', '.*');

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

/*
 * First-run setup (only accessible while the instance has no users)
 */
Route::get('/setup', 'SetupController@showSetupForm')->name('setup');
Route::post('/setup', 'SetupController@processSetup');

// Do we have catlab client id? (my own personal single sign on service)
Route::group(['middleware' => 'setup.redirect'], function () {
    if (config('services.catlab.client_id')) {
        Route::get('/login', [\App\Http\Controllers\Auth\SsoLoginController::class, 'login'])->name('login');
        Route::get('/login/callback', [\App\Http\Controllers\Auth\SsoLoginController::class, 'postLogin']);
        Route::post('/logout', [\App\Http\Controllers\Auth\SsoLoginController::class, 'logout'])->name('logout');
    } else {
        // Not set? Use default laravel authentication.
        Auth::routes();
    }
});

Route::get('/home', 'HomeController@index')->name('home')->middleware('setup.redirect');

Route::group([ 'auth' ], function() {

    Route::get('report/daily/{organisation}/{date}', [ \App\Http\Controllers\DailyReportController::class, 'dailyReport' ]);

});

/*
 * Delegated management callbacks from the CatLab accounts server
 * (register as manage_user_uri on the accounts OAuth client).
 */
Route::post('/delegated/users', [\App\Http\Controllers\DelegatedManageController::class, 'manage'])
    ->middleware('accounts.manage');
