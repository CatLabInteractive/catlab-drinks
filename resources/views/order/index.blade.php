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
</head>
<body>
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