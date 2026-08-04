<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Database not available — CatLab Drinks</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<header>
    <div class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container d-flex justify-content-between">
            <span class="navbar-brand d-flex align-items-center">
                <strong>CatLab Drinks</strong>
            </span>
        </div>
    </div>
</header>

<main class="container my-4">

    <h1>Database not available</h1>

    <p class="lead">
        The application could not @if (!empty($missingTables)) find its database tables @else connect to its database @endif.
        If you have just installed this instance, a few setup steps may still be missing.
    </p>

    <h2 class="h4 mt-4">Checklist</h2>
    <ol>
        <li class="mb-2">
            <strong>Check the database configuration.</strong><br>
            Make sure the <code>DATABASE_URL</code> environment variable (or the individual
            <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>,
            <code>DB_USERNAME</code> and <code>DB_PASSWORD</code> variables) point to a
            running database server.
        </li>
        <li class="mb-2">
            <strong>Make sure the database server is running</strong> and reachable from this
            application server.
        </li>
        <li class="mb-2">
            <strong>Run the database migrations:</strong><br>
            <code>php artisan migrate</code><br>
            On Heroku and Dokku this runs automatically on each deploy via the
            <code>release</code> process.
        </li>
    </ol>

    <p>
        See the
        <a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance">setup instructions</a>
        for a full walkthrough. Once the database is reachable, reload this page.
    </p>

</main>
</body>
</html>
