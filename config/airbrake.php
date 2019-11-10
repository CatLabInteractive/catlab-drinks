<?php

return [

    'projectId'     => env('AIRBRAKE_PROJECT_ID'),
    'projectKey'    => env('AIRBRAKE_PROJECT_KEY'),
    'environment'   => env('APP_ENV', 'production'),

    //leave the following options empty to use defaults

    'host'          => env('AIRBRAKE_HOST'), #airbrake api host e.g.: 'api.airbrake.io' or 'http://errbit.example.com
    'appVersion'    => null,
    'revision'      => null, #git revision
    'rootDirectory' => null,
    'keysBlacklist' => null, #list of keys containing sensitive information that must be filtered out
    'httpClient'    => null, #http client implementing GuzzleHttp\ClientInterface

];
