<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>CatLab Drinks</title>

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
            </div>
        </div>
    </header>

    <div class="container">

        <div class="row">

            <div class="col-md-12">

                <h1>CatLab Drinks</h1>
                <p>
                    Eenvoudig kassasysteem (met ontoegankelijke handleiding) voor evenementen. Source code
                    beschikbaar op <a href="https://github.com/catlab-drinks/catlab-drinks">GitHub</a>.
                </p>

            </div>

        </div>

        <div class="row">
            <div class="col-md-12">

                <h2>Wat is CatLab Drinks?</h2>

                <h3>Doel</h3>
                <p>
                    CatLab Drinks wilt een opensource kassasysteem zijn voor kleinschalige evenementen. Het project
                    probeert een oplossing te bieden voor:
                </p>

                <ul>
                    <li>Rekenfouten met papieren drankkaarten aan de bar vermijden</li>
                    <li>Bijhouden hoeveel en aan welke bar er verkocht wordt</li>
                    <li>Bezoekers aan tafeltjes toelaten om hun bestelling via eigen smartphone door te sturen</li>
                    <li>Mogelijkheid tot gebruik van digitale drankkaarten (NFC NTAG213 tags)</li>
                    <li>Het systeem moet blijven werken, ook als de internetverbinding even wegvalt</li>
                </ul>

                <div class="alert alert-warning">
                    De software wordt zonder enige vorm van garantie beschikbaar gesteld. Zorg steeds voor
                    een backup plan voor als het foutloopt.
                </div>

                <p>
                    <a href="{{ action('ClientController@index') }}" class="btn btn-primary">Open CatLab Drinks</a>
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <h2>Screenshots</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6"><img class="img-fluid" src="/images/screenshot1.png" /></div>
            <div class="col-md-6"><img class="img-fluid" src="/images/screenshot2.png" /></div>
        </div>

        <div class="row">
            <div class="col-md-12">

                <h3>Achtergrond</h3>
                <p>
                    CatLab drinks is ontwikkeld voor gebruik tijdens de quizzen van <a href="https://www.quizfabriek.be">De Quizfabriek</a>.
                    Op deze quizzen brengt elke ploeg een tablet of smartphone mee waarop de antwoorden worden ingegeven.
                    Met CatLab Drinks kunnen ploegen tijdens de quiz hun bestelling naar de bar sturen, die meteen in
                    actie schiet en de bestelling aan tafel brengt.
                    Door voor elke quizploeg een digitale drankkaart te voorzien en die drankkaart te linken aan het
                    quizsysteem wordt de bestelling tevens meteen betaald, waardoor er geen fouten kunnen gebeuren bij het
                    afrekenen. Bestellen aan de bar zelf blijft ook mogelijk door de digitale drankkaart te scannen.
                </p>

                <h2>Opzetten</h2>
                <h3>Account aanmaken</h3>
                <p>
                    Als je een account aanmaakt kom je op het evenementen overzicht. Elke evenement heeft een eigen
                    prijslijst die je kan aanpassen door op de naam van het evenement te klikken. Zodra je de prijslijst
                    opgemaakt hebt kan je naar <code>Bar HQ</code> gaan. Dit scherm wordt gebruikt aan de bar.
                    De bar kan 'open' of 'gesloten' zijn. Hiermee kan je 'remote bestellingen' (dus bestellingen aan
                    tafel) aan of uit zetten (dit kan handig zijn tijdens bijvoorbeeld de pauze, waarbij er niet aan
                    tafel besteld wordt).
                </p>

                <h3>Digitale drankkaarten</h3>
                <p>
                    CatLab drinks implementeert een closed loop RFIC/NFC betaalsysteem.
                </p>

                <p>
                    Om digitale drankkaarten te gebruiken heb je voor elke bar een acr122u kaartlezer nodig (andere toestellen
                    werken mogelijk ook) en voor elke bezoeker een NTAG213 chip nodig. (Wij voorzien 1 kaart per quizploeg
                    zodat we elke kaart aan een quizploeg kunnen hangen.)
                    De communicatie tussen de kaartlezer en de browser gebeurt over een socket.io verbinding. Daarvoor
                    dien je een <a href="https://github.com/catlab-drinks/nfc-socketio">service te installeren</a>. Wij
                    gebruiken een Raspberry pi, maar de service kan ook op bv een laptop draaien.
                </p>

            </div>

        </div>

    </div>

    </body>
</html>
