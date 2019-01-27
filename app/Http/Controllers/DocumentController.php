<?php

namespace App\Http\Controllers;

use Config;
use Response;

/**
 * Class SwaggerController
 * @package App\Http\Controllers
 */
class DocumentController extends Controller
{
    /**
     * @return \Illuminate\Http\Response
     */
    public function swagger()
    {
        $parameters = [];

        $parameters['oauth2_client_id'] = Config::get('charon.ui.client_id');
        $parameters['oauth2_redirect_url'] = url('docs/oauth2');

        return Response::view('api.swagger', $parameters);
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function oauth2Redirect()
    {
        return Response::view('api.oauth2-redirect');
    }
}
