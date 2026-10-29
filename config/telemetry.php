<?php

use App\Support\Telemetry;

return [

    /*
    |--------------------------------------------------------------------------
    | Usage reporting (opt-in)
    |--------------------------------------------------------------------------
    |
    | When enabled, the instance sends an anonymous heartbeat to CatLab's
    | Matomo at most once per day: a random instance ID, software versions
    | and a few counts (organisations, users, devices, events, orders).
    | No names, emails, menu items or amounts. Off by default.
    | Run `php artisan drinks:report-usage --dry-run` to see the payload.
    |
    */
    'usage' => [
        'enabled' => (bool) env('USAGE_REPORTING', false),
        'matomo_url' => env('USAGE_REPORTING_MATOMO_URL', Telemetry::CATLAB_MATOMO_URL),
        'matomo_site_id' => env('USAGE_REPORTING_MATOMO_SITE_ID', Telemetry::CATLAB_MATOMO_SITE_ID),
        'interval_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error reporting (opt-in)
    |--------------------------------------------------------------------------
    |
    | When enabled and no own AIRBRAKE_* project is configured, errors are
    | sent to CatLab's Airbrake-compatible (Errbit) project. See
    | config/airbrake.php, which resolves the effective project.
    |
    */
    'errors' => [
        'enabled' => (bool) env('ERROR_REPORTING', false),
    ],

];
