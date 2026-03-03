<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Getting Started — CatLab Drinks Android App</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @include('blocks.favicon')
</head>
<body>
<header>
    <div class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container d-flex justify-content-between">
            <a href="/" class="navbar-brand d-flex align-items-center">
                <strong>CatLab Drinks</strong>
            </a>
        </div>
    </div>
</header>

<main class="container my-4">
    <h1>Getting started: link the Android app to your CatLab Drinks instance</h1>
    <p class="lead">
        Follow these practical steps to connect a new Android POS device to your own CatLab Drinks environment.
    </p>

    <ol>
        <li>Open the Manage app and go to <strong>Devices</strong>.</li>
        <li>Create a new connect request and copy or scan the generated connection URL/QR code.</li>
        <li>Install/open the Android app on your device and paste/scan the connection URL.</li>
        <li>A pairing code appears on Android. Enter it in Manage to complete pairing.</li>
        <li>After approval, the Android app receives its access token and opens the POS screens.</li>
    </ol>

    <div class="alert alert-info">
        Tip: if the Android app receives a 401 later, it automatically clears its local token and returns to the pairing screen.
    </div>

    <hr>

    <h2>Screenshots</h2>
    <p>These screenshots show key moments in the pairing flow.</p>
    <div class="row">
        <div class="col-md-6 mb-4">
            <img class="img-fluid rounded border" src="/images/screenshot1.png" alt="CatLab Drinks POS overview screenshot">
        </div>
        <div class="col-md-6 mb-4">
            <img class="img-fluid rounded border" src="/images/screenshot2.png" alt="CatLab Drinks POS payment dialog screenshot">
        </div>
        <div class="col-md-6 mb-4">
            <img class="img-fluid rounded border" src="/images/screenshot3.png" alt="CatLab Drinks POS overview screenshot (additional example)">
        </div>
        <div class="col-md-6 mb-4">
            <img class="img-fluid rounded border" src="/images/screenshot4.png" alt="CatLab Drinks POS payment dialog screenshot (additional example)">
        </div>
    </div>
</main>
</body>
</html>
