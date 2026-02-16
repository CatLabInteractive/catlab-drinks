<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, width=device-width, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">

    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CatLab Drinks</title>

    <base href="/pos/" />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Styles -->
    <link href="{{ asset('res/pos.css') }}" rel="stylesheet">

@include('blocks.favicon')

<body>

@include('blocks.gtm')
@include('blocks.airbrake')

<div id="app">
    <app></app>
</div>

<script src="{{ mix('res/pos.js') }}"></script>
</body>
</html>
