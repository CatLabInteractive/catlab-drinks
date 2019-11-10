<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, width=device-width, maximum-scale=1.0, user-scalable=0">

    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CatLab Drinks</title>

    <base href="/client/" />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Styles -->
    <link href="{{ asset('res/sales/css/app.css') }}" rel="stylesheet">

@include('blocks.favicon')

<body>

@include('blocks.gtm')
@include('blocks.airbrake')

<script>
    var ORGANISATION_ID = {{ $organisation->id }};
</script>

<div id="app">
    @yield('content')
</div>

<script src="{{ mix('res/sales/js/app.js') }}"></script>
</body>
</html>
