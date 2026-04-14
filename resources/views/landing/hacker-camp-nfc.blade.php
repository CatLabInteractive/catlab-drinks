<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Open-source NFC payment infrastructure for hacker camps and tech events. Source your own hardware, deploy on your own server, and inspect every line of code.">
    <meta name="keywords" content="hacker camp NFC payment, tech event cashless system, open-source NFC infrastructure, DIY festival payment infrastructure, open-architecture payment, self-hosted POS system">
    <link rel="canonical" href="{{ url('/hacker-camp-nfc') }}">

    <title>NFC Payment Infrastructure for Hacker Camps — CatLab Drinks</title>

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
                "name": "Can I audit the full source code of the payment system?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. CatLab Drinks is released under the GNU GPL v3 license. The entire codebase \u2014 backend, POS frontend, NFC card logic, and cryptographic signing \u2014 is publicly available on GitHub for inspection, modification, and redistribution."
                }
            },
            {
                "@type": "Question",
                "name": "What cryptography does the NFC card system use?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Each POS terminal generates an ECDSA P-192 key pair. Card data (balance, transaction count, timestamps, and the hardware UID) is signed with the terminal\u2019s private key. Other terminals verify signatures using approved public keys distributed by the server. P-192 was chosen to fit signatures within the 144-byte NTAG213 memory constraint."
                }
            },
            {
                "@type": "Question",
                "name": "Can I source my own NFC hardware?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Absolutely. The system uses standard NTAG213 tags available from any electronics supplier. For readers, any NFC-capable Android device or ACR122U-compatible USB reader works. There is zero vendor lock-in on hardware."
                }
            },
            {
                "@type": "Question",
                "name": "How do I integrate CatLab Drinks with my own infrastructure?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "The system exposes a full REST API documented with Swagger. Deploy via Docker, Heroku, or any PHP-capable server. The NFC companion service for USB readers communicates over socket.io. All protocols are documented and open."
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
            <h1>NFC Payment Infrastructure for Hacker Camps</h1>
            <p class="lead">Open architecture. Open source. Bring your own hardware.</p>
            <p>
                Hacker camps and tech events demand transparency and control. CatLab Drinks is a
                GPL-licensed cashless payment system you can deploy, inspect, and modify. Source your
                own NTAG213 tags in bulk, run the server on your own metal, and integrate with your
                existing infrastructure via a documented REST API.
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
            <h2>Built for People Who Read the Source</h2>
            <p>
                No black boxes. The cryptographic model, card data format, and API surface are fully
                documented and open for audit. Here's what's under the hood.
            </p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <h4>🔑 Per-Device ECDSA Keys</h4>
            <p>
                Each POS terminal generates its own P-192 key pair. Signatures fit within NTAG213's
                144-byte memory. Public keys require explicit admin approval — no trust-on-first-use.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🛠️ Raw Hardware Sourcing</h4>
            <p>
                Use any NTAG213 NFC tags from any supplier. ACR122U or compatible USB readers for
                desktop setups. Android NFC for mobile terminals. No proprietary hardware required.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📡 Socket.IO NFC Bridge</h4>
            <p>
                The companion <a href="https://github.com/catlab-drinks/nfc-socketio">nfc-socketio</a>
                service bridges USB NFC readers to the browser POS via socket.io. Run it on a Raspberry
                Pi or any Linux box.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>📄 Full REST API</h4>
            <p>
                Both the management and device APIs are documented with Swagger. Integrate with your
                own dashboards, ticketing systems, or data pipelines.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🐳 Docker-Ready</h4>
            <p>
                Deploy with Docker, Docker Compose, Heroku buildpacks, or bare-metal PHP+Apache. The
                Dockerfile and Procfile are included in the repository.
            </p>
        </div>
        <div class="col-md-4 mb-3">
            <h4>🔓 GPL v3 Licensed</h4>
            <p>
                Free as in freedom. Fork it, patch it, redistribute it. The full source — backend,
                frontend, NFC logic, and crypto — is on GitHub.
            </p>
        </div>
    </div>

    <hr />

    <div class="row">
        <div class="col-md-12">
            <h2>Technical Details</h2>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <h4>Card Data Format (V1)</h4>
            <p>
                Each NFC card stores 85 bytes of signed data in an NDEF external record:
            </p>
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Field</th>
                        <th>Size</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Version</td><td>1 byte</td><td>Format version (0x01)</td></tr>
                    <tr><td>Device ID</td><td>3 bytes</td><td>Signing terminal identifier</td></tr>
                    <tr><td>Balance</td><td>4 bytes</td><td>Signed 32-bit integer (cents)</td></tr>
                    <tr><td>Transaction count</td><td>4 bytes</td><td>Unsigned 32-bit monotonic counter</td></tr>
                    <tr><td>Timestamp</td><td>4 bytes</td><td>Unsigned 32-bit Unix timestamp</td></tr>
                    <tr><td>Previous transactions</td><td>20 bytes</td><td>5 × signed 32-bit amounts</td></tr>
                    <tr><td>Discount</td><td>1 byte</td><td>Discount flag</td></tr>
                    <tr><td>ECDSA signature</td><td>48 bytes</td><td>P-192 signature (24 r + 24 s)</td></tr>
                </tbody>
            </table>
            <p>
                The signature covers <code>version + deviceId + payload + cardHardwareUid</code>.
                Including the hardware UID (read from the NFC chip, not stored in the data) prevents
                cross-card replay attacks.
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
                <h5>Can I audit the full source code of the payment system?</h5>
                <p>Yes. CatLab Drinks is released under the GNU GPL v3 license. The entire codebase — backend, POS frontend, NFC card logic, and cryptographic signing — is publicly available on GitHub for inspection, modification, and redistribution.</p>
            </div>
            <div class="mb-3">
                <h5>What cryptography does the NFC card system use?</h5>
                <p>Each POS terminal generates an ECDSA P-192 key pair. Card data (balance, transaction count, timestamps, and the hardware UID) is signed with the terminal's private key. Other terminals verify signatures using approved public keys distributed by the server. P-192 was chosen to fit signatures within the 144-byte NTAG213 memory constraint.</p>
            </div>
            <div class="mb-3">
                <h5>Can I source my own NFC hardware?</h5>
                <p>Absolutely. The system uses standard NTAG213 tags available from any electronics supplier. For readers, any NFC-capable Android device or ACR122U-compatible USB reader works. There is zero vendor lock-in on hardware.</p>
            </div>
            <div class="mb-3">
                <h5>How do I integrate CatLab Drinks with my own infrastructure?</h5>
                <p>The system exposes a full REST API documented with Swagger. Deploy via Docker, Heroku, or any PHP-capable server. The NFC companion service for USB readers communicates over socket.io. All protocols are documented and open.</p>
            </div>
        </div>
    </div>

    <hr />

    <div class="row mb-4">
        <div class="col-md-12">
            <p>
                <a href="/">← Back to CatLab Drinks homepage</a> ·
                <a href="/whitepaper">Read the Security Whitepaper</a>
            </p>
        </div>
    </div>

</div>

</body>
</html>
