<?php

// The instance's own AIRBRAKE_* project, or CatLab's when ERROR_REPORTING is on.
$project = \App\Support\Telemetry::airbrakeProject();

return [

    'projectId'     => $project['projectId'],
    'projectKey'    => $project['projectKey'],
    'environment'   => env('APP_ENV', 'production'),

    //leave the following options empty to use defaults

    'host'          => $project['host'], #airbrake api host e.g.: 'api.airbrake.io' or 'http://errbit.example.com
    'appVersion'    => null,
    'revision'      => null, #git revision
    'rootDirectory' => null,
    'keysBlocklist' => $project['keysBlocklist'], #list of keys containing sensitive information that must be filtered out
    'httpClient'    => null, #http client implementing GuzzleHttp\ClientInterface

];
