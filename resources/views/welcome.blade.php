<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('welcome.title') }} — {{ __('welcome.tagline') }}</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet" type="text/css">

        <link href="{{ asset('css/app.css') }}" rel="stylesheet">

        @include('blocks.favicon')

    @if(config('services.gtm'))
        <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','{{ config('services.gtm') }}');</script>
            <!-- End Google Tag Manager -->
        @endif
    </head>
    <body>

    @if(config('services.gtm'))
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm') }}"
                          height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

    <header>
        <div class="navbar navbar-dark bg-dark shadow-sm">
            <div class="container d-flex justify-content-between">
                <a href="#" class="navbar-brand d-flex align-items-center">
                    <strong>CatLab Drinks</strong>
                </a>
                <div class="d-flex align-items-center">
                    <span class="text-light mr-2">{{ __('welcome.language') }}:</span>
                    @foreach(['en' => 'English', 'nl' => 'Nederlands', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español'] as $code => $label)
                        @if($code === app()->getLocale())
                            <span class="btn btn-sm btn-light ml-1">{{ $label }}</span>
                        @else
                            <a href="?lang={{ $code }}" class="btn btn-sm btn-outline-light ml-1">{{ $label }}</a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </header>

    <div class="container">

        <div class="row mt-4">
            <div class="col-lg-8">
                <h1>{{ __('welcome.title') }}</h1>
                <p class="lead">{{ __('welcome.tagline') }}</p>
                <p>{{ __('welcome.subtitle') }}</p>
                <p>
                    <a href="{{ action('ClientController@manage') }}" class="btn btn-primary btn-lg">{{ __('welcome.open_web_app') }}</a>
                    <a href="https://play.google.com/store/apps/details?id=eu.catlab.drinks" class="btn btn-success btn-lg">{{ __('welcome.install_android') }}</a>
                    <a href="https://github.com/CatLabInteractive/catlab-drinks" class="btn btn-outline-dark btn-lg">{{ __('welcome.view_on_github') }}</a>
                </p>
            </div>
        </div>

        <hr />

        <div class="row">
            <div class="col-md-12">
                <h2>{{ __('welcome.why_title') }}</h2>
                <p>{{ __('welcome.why_intro') }}</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <h4>✅ {{ __('welcome.why_1_title') }}</h4>
                <p>{{ __('welcome.why_1_desc') }}</p>
            </div>
            <div class="col-md-4 mb-3">
                <h4>📊 {{ __('welcome.why_2_title') }}</h4>
                <p>{{ __('welcome.why_2_desc') }}</p>
            </div>
            <div class="col-md-4 mb-3">
                <h4>📱 {{ __('welcome.why_3_title') }}</h4>
                <p>{{ __('welcome.why_3_desc') }}</p>
            </div>
            <div class="col-md-4 mb-3">
                <h4>💳 {{ __('welcome.why_4_title') }}</h4>
                <p>{{ __('welcome.why_4_desc') }}</p>
            </div>
            <div class="col-md-4 mb-3">
                <h4>📡 {{ __('welcome.why_5_title') }}</h4>
                <p>{{ __('welcome.why_5_desc') }}</p>
            </div>
            <div class="col-md-4 mb-3">
                <h4>🔓 {{ __('welcome.why_6_title') }}</h4>
                <p>{{ __('welcome.why_6_desc') }}</p>
            </div>
        </div>

        <hr />

        <div class="row">
            <div class="col-md-12">
                <h2>{{ __('welcome.screenshots_title') }}</h2>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6"><img class="img-fluid" src="/images/screenshot1.png" /></div>
            <div class="col-md-6"><img class="img-fluid" src="/images/screenshot2.png" /></div>
        </div>

        <hr />

        <div class="row">
            <div class="col-md-12">
                <h2>{{ __('welcome.deploy_title') }}</h2>
                <p>{{ __('welcome.deploy_intro') }}</p>
                <ol>
                    <li>{{ __('welcome.deploy_step_1') }}</li>
                    <li>{{ __('welcome.deploy_step_2') }}</li>
                    <li>{{ __('welcome.deploy_step_3') }}</li>
                    <li>{{ __('welcome.deploy_step_4') }}</li>
                </ol>
                <p>{{ __('welcome.deploy_docker') }}</p>
            </div>
        </div>

        <hr />

        <div class="row">
            <div class="col-md-12">
                <h2>{{ __('welcome.nfc_title') }}</h2>
                <p>{{ __('welcome.nfc_intro') }}</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <h4>{{ __('welcome.nfc_1_title') }}</h4>
                <p>{{ __('welcome.nfc_1_desc') }}</p>
            </div>
            <div class="col-md-6 mb-3">
                <h4>{{ __('welcome.nfc_2_title') }}</h4>
                <p>{{ __('welcome.nfc_2_desc') }}</p>
            </div>
            <div class="col-md-6 mb-3">
                <h4>{{ __('welcome.nfc_3_title') }}</h4>
                <p>{{ __('welcome.nfc_3_desc') }}</p>
            </div>
            <div class="col-md-6 mb-3">
                <h4>{{ __('welcome.nfc_4_title') }}</h4>
                <p>{{ __('welcome.nfc_4_desc') }}</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <p>
                    {{ __('welcome.nfc_companion') }}
                    <a href="https://github.com/catlab-drinks/nfc-socketio">{{ __('welcome.nfc_companion_link') }}</a>
                    {{ __('welcome.nfc_companion_after') }}
                </p>
            </div>
        </div>

        <hr />

        <div class="row mb-4">
            <div class="col-md-12">
                <h2>{{ __('welcome.license_title') }}</h2>
                <p>{{ __('welcome.license_text') }}</p>
                <div class="alert alert-warning">
                    {{ __('welcome.license_warranty') }}
                </div>
            </div>
        </div>

    </div>

    </body>
</html>
