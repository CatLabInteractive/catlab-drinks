<?php

use App\Support\Telemetry;

return [

    /*
    |--------------------------------------------------------------------------
    | Usage reporting (opt-out)
    |--------------------------------------------------------------------------
    |
    | The instance sends an anonymous heartbeat to CatLab's Matomo at most
    | once per day: a random instance ID, software versions and a few counts
    | (organisations, users, devices, events, orders). No names, emails, menu
    | items or amounts. On by default, except when APP_ENV is local or
    | testing. Set USAGE_REPORTING=false to turn it off.
    | Run `php artisan drinks:report-usage --dry-run` to see the payload.
    |
    */
    'usage' => [
        'enabled' => Telemetry::isOn('USAGE_REPORTING'),
        'matomo_url' => env('USAGE_REPORTING_MATOMO_URL', Telemetry::CATLAB_MATOMO_URL),
        'matomo_site_id' => env('USAGE_REPORTING_MATOMO_SITE_ID', Telemetry::CATLAB_MATOMO_SITE_ID),
        'interval_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error reporting (opt-out)
    |--------------------------------------------------------------------------
    |
    | When no own AIRBRAKE_* project is configured, errors are sent to
    | CatLab's Airbrake-compatible (Errbit) project. On by default, except
    | when APP_ENV is local or testing. Set ERROR_REPORTING=false to turn it
    | off. See config/airbrake.php, which resolves the effective project.
    | `to_catlab` tells if errors go to CatLab's project right now.
    |
    */
    'errors' => [
        'enabled' => Telemetry::isOn('ERROR_REPORTING'),
        'to_catlab' => Telemetry::airbrakeProject()['catlab'],
    ],

];
