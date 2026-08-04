<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Production organisations
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of organisation IDs that use this instance in
    | production. When set, all other organisations see a warning in the
    | admin panel that this instance is for testing only. When empty, the
    | instance is considered private and all organisations are production.
    |
    */
    'production_organisation_ids' => \App\Support\InstanceSettings::parseIdList(
        env('PRODUCTION_ORGANISATION_IDS')
    ),

    /*
    |--------------------------------------------------------------------------
    | Open registration
    |--------------------------------------------------------------------------
    |
    | By default, registration closes automatically once the first user has
    | been created. Set to true to keep public registration open (as on the
    | shared instance drinks.catlab.eu).
    |
    */
    'registration_open' => (bool) env('REGISTRATION_OPEN', false),

];
