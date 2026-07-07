<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A free, open-source cashless payment system built for student unions. Budget-friendly NFC tags, volunteer-friendly setup, and zero hardware lock-in.">
    <meta name="keywords" content="student union cashless payment, budget NFC payment system, volunteer-friendly POS, student event bar automation, no hardware lock-in, open-source cashless">
    <link rel="canonical" href="{{ url('/student-union-cashless') }}">

    <title>Cashless Payments for Student Unions — CatLab Drinks</title>

    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet" type="text/css">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @include('blocks.favicon')

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "How much does it cost to set up a cashless system for a student event?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CatLab Drinks is free and open-source software. The only cost is the NFC tags themselves, which start at around €0.10 each. You can use any Android phone or tablet you already own as a POS terminal."
                }
            },
            {
                "@type": "Question",
                "name": "Can volunteers run the system without technical training?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. The POS interface is designed to be simple — volunteers tap menu items, confirm the order, and optionally scan an NFC card. Pairing a device takes under two minutes with the QR code flow."
                }
            },
            {
                "@type": "Question",
                "name": "Do we need to buy specialised hardware?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "No. CatLab Drinks is hardware-agnostic. Any NFC-enabled Android device works as a terminal. For desktop setups, any compatible USB NFC reader can be used. Standard NTAG213 tags work as payment cards."
                }
            },
            {
                "@type": "Question",
                "name": "Can we self-host the system on our own server?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Absolutely. CatLab Drinks is designed for self-hosting. Deploy with Docker, Heroku, or any PHP-capable server. Your data stays under your control at all times."
                }
            }
        ]
    }
    </script>

    @if(config('services.gtm'))
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ config('services.gtm') }}');</script>
    @endif
</head>
<body>

@if(config('services.gtm'))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm') }}"
                      height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif

<header>
    <div class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container d-flex justify-content-between">
            <a href="/" class="navbar-brand d-flex align-items-center">
                <strong>CatLab Drinks</strong>
            </a>
        </div>
    </div>
</header>

<div class="container">

    <div class="row mt-4">
        <div class="col-lg-8">
            <h1>Cashless Payments for Student Unions</h1>
            <p class="lead">Run a professional cashless bar at your next campus event — without the professional price tag.</p>
            <p>
                Student unions run on tight budgets and volunteer power. CatLab Drinks is a free, open-source bar
                automation system that turns cheap NFC tags and the Android phones you already have into a
                fully-featured cashless payment infrastructure. No vendor contracts, no recurring fees, no
                hardware lock-in.
            </p>
            <p>
                <a href="{{ action('ClientController@manage') }}" class="btn btn-primary btn-lg">Open Web App</a>
                <a href="https://play.google.com/store/apps/details?id=eu.catlab.drinks" class="btn btn-success btn-lg">Install Android App</a>
                <a href="https://github.com/CatLabInteractive/catlab-drinks" class="btn btn-outline-dark btn-lg">View on GitHub</a>
            </p>
        </div>
    </div>

    <hr />

    <div class="row">
        <div class="col-md-12">
            <h2>Why Student Unions Choose CatLab Drinks</h2>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <h4>💰 Minimal Cost</h4>
            <p>
                The software is completely free. NFC tags cost as little as €0.10 each — a fraction of the
                price of proprietary wristbands. Use the Android devices your team already owns as POS
                terminals.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🙋 Volunteer-Friendly</h4>
            <p>
                The POS interface is simple enough for anyone to use after a two-minute walkthrough. Pair a
                new device by scanning a QR code. No training sessions or technical knowledge required.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🔓 Zero Lock-In</h4>
            <p>
                Self-host on your own server or use a free-tier cloud service. Standard NFC tags, standard
                Android devices, open-source code. Move to a different solution any time — your data is
                always yours.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📊 Real-Time Sales Reports</h4>
            <p>
                Treasurers get instant visibility into revenue, top-selling items, and per-bar breakdowns.
                Export data for your financial reporting with no extra tools needed.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📡 Works Offline</h4>
            <p>
                Spotty campus WiFi? No problem. The POS system processes transactions offline and syncs
                when the connection returns. Balances are stored directly on the NFC cards.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🔐 Cryptographic Security</h4>
            <p>
                Each terminal signs card data with its own ECDSA key pair. Signatures are verified
                organisation-wide, preventing forgery and ensuring every transaction is tamper-proof.
            </p>
        </div>
    </div>

    <hr />

    <div class="row">
        <div class="col-md-12">
            <h2>Frequently Asked Questions</h2>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="mb-3">
                <h5>How much does it cost to set up a cashless system for a student event?</h5>
                <p>CatLab Drinks is free and open-source software. The only cost is the NFC tags themselves, which start at around €0.10 each. You can use any Android phone or tablet you already own as a POS terminal.</p>
            </div>
            <div class="mb-3">
                <h5>Can volunteers run the system without technical training?</h5>
                <p>Yes. The POS interface is designed to be simple — volunteers tap menu items, confirm the order, and optionally scan an NFC card. Pairing a device takes under two minutes with the QR code flow.</p>
            </div>
            <div class="mb-3">
                <h5>Do we need to buy specialised hardware?</h5>
                <p>No. CatLab Drinks is hardware-agnostic. Any NFC-enabled Android device works as a terminal. For desktop setups, any compatible USB NFC reader can be used. Standard NTAG213 tags work as payment cards.</p>
            </div>
            <div class="mb-3">
                <h5>Can we self-host the system on our own server?</h5>
                <p>Absolutely. CatLab Drinks is designed for self-hosting. Deploy with Docker, Heroku, or any PHP-capable server. Your data stays under your control at all times.</p>
            </div>
        </div>
    </div>

    <hr />

    <div class="row mb-4">
        <div class="col-md-12">
            <p>
                <a href="/">← Back to CatLab Drinks homepage</a>
            </p>
        </div>
    </div>

</div>

</body>
</html>
