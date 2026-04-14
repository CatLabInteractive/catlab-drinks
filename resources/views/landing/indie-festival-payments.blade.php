<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Break free from expensive vendor ecosystems. CatLab Drinks is an open-source, hardware-agnostic cashless payment system for indie festivals. Use budget NFC tags and your own devices.">
    <meta name="keywords" content="indie festival cashless payment, budget festival NFC system, open-source festival POS, hardware-agnostic payment system, DIY festival payment infrastructure, self-hosted bar automation">
    <link rel="canonical" href="{{ url('/indie-festival-payments') }}">

    <title>Cashless Payments for Indie Festivals — CatLab Drinks</title>

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
                "name": "Can I run a cashless festival without expensive vendor contracts?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. CatLab Drinks is free, open-source software. You deploy it on your own server and use standard NTAG213 NFC tags that cost as little as \u20ac0.10 each. There are no licensing fees, no per-transaction charges, and no mandatory hardware bundles."
                }
            },
            {
                "@type": "Question",
                "name": "What NFC tags work with this system?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Any standard NTAG213 NFC tag or sticker works. You can source them from any electronics supplier worldwide. There is no requirement for proprietary wristbands or vendor-specific chips."
                }
            },
            {
                "@type": "Question",
                "name": "How many POS terminals can I run simultaneously?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "There is no hard limit. Each Android device or USB NFC reader setup acts as an independent terminal. Add as many bars and terminals as your event needs."
                }
            },
            {
                "@type": "Question",
                "name": "What happens if the internet goes down during the festival?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "The POS keeps working. Balances are stored on the NFC cards themselves, so transactions process locally. When connectivity returns, everything syncs automatically to the server."
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
            <h1>Cashless Payments for Indie Festivals</h1>
            <p class="lead">Own your payment infrastructure. No vendor lock-in, no per-transaction fees, no mandatory hardware bundles.</p>
            <p>
                Indie festivals shouldn't have to hand over a slice of every drink sale just to go cashless.
                CatLab Drinks is an open-source, hardware-agnostic payment system that lets you build your
                own DIY festival payment infrastructure using off-the-shelf NFC tags that cost cents — not euros.
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
            <h2>Break Free from Expensive Ecosystems</h2>
            <p>
                Traditional cashless vendors lock you into their hardware, their software, and their pricing.
                CatLab Drinks takes the opposite approach: everything is open, standard, and under your control.
            </p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <h4>🏷️ €0.10 NFC Tags</h4>
            <p>
                Use standard NTAG213 NFC tags or stickers sourced from any supplier. No proprietary
                wristbands or vendor-specific chips required. Budget a fraction of what traditional
                systems charge per attendee.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📱 Your Own Devices</h4>
            <p>
                Any Android phone or tablet with NFC becomes a POS terminal. Use what you already have
                or buy affordable second-hand devices. Desktop setups use any USB NFC reader.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🖥️ Self-Hosted</h4>
            <p>
                Deploy on your own server, a VPS, or a free-tier cloud platform. Your attendee data
                and transaction history stay on infrastructure you control. No third-party data sharing.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📊 Full Sales Visibility</h4>
            <p>
                Real-time dashboards show revenue per bar, top-selling items, and transaction volumes.
                Make informed decisions during the festival and generate post-event reports instantly.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📡 Reliable Offline Mode</h4>
            <p>
                Field conditions are unpredictable. The POS system continues processing transactions
                offline because balances live on the cards. Data syncs seamlessly when the connection
                is restored.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🔐 Bank-Grade Signing</h4>
            <p>
                Every card transaction is signed with per-device ECDSA cryptographic keys. No shared
                secrets, no single points of failure. An admin must approve every terminal key before
                it can write to cards.
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
                <h5>Can I run a cashless festival without expensive vendor contracts?</h5>
                <p>Yes. CatLab Drinks is free, open-source software. You deploy it on your own server and use standard NTAG213 NFC tags that cost as little as €0.10 each. There are no licensing fees, no per-transaction charges, and no mandatory hardware bundles.</p>
            </div>
            <div class="mb-3">
                <h5>What NFC tags work with this system?</h5>
                <p>Any standard NTAG213 NFC tag or sticker works. You can source them from any electronics supplier worldwide. There is no requirement for proprietary wristbands or vendor-specific chips.</p>
            </div>
            <div class="mb-3">
                <h5>How many POS terminals can I run simultaneously?</h5>
                <p>There is no hard limit. Each Android device or USB NFC reader setup acts as an independent terminal. Add as many bars and terminals as your event needs.</p>
            </div>
            <div class="mb-3">
                <h5>What happens if the internet goes down during the festival?</h5>
                <p>The POS keeps working. Balances are stored on the NFC cards themselves, so transactions process locally. When connectivity returns, everything syncs automatically to the server.</p>
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
