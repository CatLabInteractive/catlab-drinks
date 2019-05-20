<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CatLab Drinks</title>

    <base href="{{ $baseUrl }}" />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Styles -->
    <link href="{{ asset('res/clients/css/app.css') }}" rel="stylesheet">

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

<div id="app" style="min-height: 1000px">
    <app></app>
</div>

<script type="text/javascript">
    var BASE_URL = '{{$baseUrl}}';
    var EVENT_TOKEN = '{{$token }}';
</script>
<script src="{{ mix('res/clients/js/app.js') }}"></script>
</body>
</html>