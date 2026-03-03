<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Getting Started — CatLab Drinks</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @include('blocks.favicon')

    <style>
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #343a40;
            color: #fff;
            border-radius: 50%;
            font-weight: bold;
            font-size: .9rem;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .step-heading {
            display: flex;
            align-items: center;
        }
        .screenshot-suggestion {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            color: #6c757d;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
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

    <h1>Getting Started</h1>
    <p class="lead">
        This guide explains how CatLab Drinks works and walks you through linking the Android app to your instance.
    </p>

    <hr>

    {{-- ================================================================== --}}
    {{-- OVERVIEW --}}
    {{-- ================================================================== --}}
    <h2>How CatLab Drinks works</h2>
    <p>
        CatLab Drinks is a bar automation and point-of-sale (POS) system. It consists of three parts:
    </p>
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">🌐 Management panel</h5>
                    <p class="card-text">
                        The web-based back-office at <code>/manage/</code>. Here you create events,
                        configure menus and prices, register POS devices, view sales reports and manage
                        attendees. Requires a user account with login.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">📱 POS terminal</h5>
                    <p class="card-text">
                        The Android app (or browser-based POS at <code>/pos/</code>). This is what
                        bartenders use to take orders: tap menu items, confirm, and optionally scan
                        NFC cards for cashless payment. Each terminal is a <em>device</em> that must
                        be paired first.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">🍹 Client order screen</h5>
                    <p class="card-text">
                        An optional public screen at <code>/order/</code> where customers can follow
                        the status of their order in real-time.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <hr>

    {{-- ================================================================== --}}
    {{-- PREREQUISITES --}}
    {{-- ================================================================== --}}
    <h2>Prerequisites</h2>
    <ul>
        <li>A running CatLab Drinks instance (self-hosted or at <a href="https://drinks.catlab.eu">drinks.catlab.eu</a>).</li>
        <li>An administrator account so you can access the management panel.</li>
        <li>
            The <strong>CatLab Drinks</strong> Android app installed on your tablet or phone —
            <a href="https://play.google.com/store/apps/details?id=eu.catlab.drinks">download from Google Play</a>.
        </li>
        <li>Both the server and the Android device must be on the same network (or the server must be publicly reachable).</li>
    </ul>

    <hr>

    {{-- ================================================================== --}}
    {{-- PAIRING GUIDE --}}
    {{-- ================================================================== --}}
    <h2>Pairing a new POS device — step by step</h2>
    <p>
        Every POS device (Android app or browser) must be <em>paired</em> with your CatLab Drinks
        instance before it can take orders. Pairing uses a secure QR-code + pairing-code flow so that
        only devices you explicitly approve get access.
    </p>

    {{-- Step 1 --}}
    <div class="mb-4">
        <h4 class="step-heading"><span class="step-number">1</span> Open the Devices page in the management panel</h4>
        <p>
            Log in to your CatLab Drinks instance and navigate to <strong>Devices</strong> in the
            management panel. You will see a list of all registered POS terminals (if any).
        </p>
        <!-- screenshot: management panel → Devices page showing the device table and the ＋ button -->
        <div class="screenshot-suggestion">📷 Screenshot suggestion: the Devices page in the management panel, showing the device list and the green <strong>＋</strong> button</div>
    </div>

    {{-- Step 2 --}}
    <div class="mb-4">
        <h4 class="step-heading"><span class="step-number">2</span> Create a connect request</h4>
        <p>
            Click the green <strong>＋</strong> button. A modal dialog appears with a <strong>QR code</strong>
            and a <strong>connection URL</strong>. This is a one-time token that the Android app will
            use to identify your server.
        </p>
        <p>
            Keep this dialog open — you will need it in the next step.
        </p>
        <!-- screenshot: the "Connect & authenticate a device" modal showing the QR code and URL -->
        <div class="screenshot-suggestion">📷 Screenshot suggestion: the <em>"Connect &amp; authenticate a device"</em> modal showing the QR code and connection URL field</div>
    </div>

    {{-- Step 3 --}}
    <div class="mb-4">
        <h4 class="step-heading"><span class="step-number">3</span> Scan the QR code with the Android app</h4>
        <p>
            Open the CatLab Drinks Android app on your device. Because it has no token yet,
            it shows the <strong>Connect Device</strong> screen with two options:
        </p>
        <ul>
            <li><strong>Scan QR Code</strong> — point the camera at the QR code shown on your management screen.</li>
            <li><strong>Enter Token Manually</strong> — copy the connection URL from the management modal and paste it.</li>
        </ul>
        <p>
            The app contacts the server and registers itself as a new (unconfirmed) device.
        </p>
        <!-- screenshot: Android app showing the Connect Device screen with "Scan QR Code" and "Enter Token Manually" buttons -->
        <div class="screenshot-suggestion">📷 Screenshot suggestion: the Android app's <em>Connect Device</em> screen with the <strong>Scan QR Code</strong> / <strong>Enter Token Manually</strong> buttons</div>
    </div>

    {{-- Step 4 --}}
    <div class="mb-4">
        <h4 class="step-heading"><span class="step-number">4</span> Enter the pairing code</h4>
        <p>
            Because this is a brand-new device, the server generates a random <strong>6-digit pairing code</strong>
            that is displayed on the Android app. At the same time, the management modal updates and asks you to
            enter this code together with a descriptive <strong>device name</strong> (e.g. "Bar Terminal 1").
        </p>
        <p>
            This two-factor confirmation prevents rogue devices from pairing without your knowledge.
        </p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <!-- screenshot: Android app showing the 6-digit pairing code -->
                <div class="screenshot-suggestion">📷 Screenshot suggestion: the Android app displaying the <strong>6-digit pairing code</strong> and "waiting for confirmation" spinner</div>
            </div>
            <div class="col-md-6 mb-3">
                <!-- screenshot: management modal with pairing code input + device name field -->
                <div class="screenshot-suggestion">📷 Screenshot suggestion: the management modal with the <strong>Pairing Code</strong> input field and <strong>Device Name</strong> field</div>
            </div>
        </div>
    </div>

    {{-- Step 5 --}}
    <div class="mb-4">
        <h4 class="step-heading"><span class="step-number">5</span> Device is paired — start taking orders!</h4>
        <p>
            Once the pairing code is accepted, the server issues an <strong>access token</strong> to the Android
            app. The app automatically reloads and shows the <strong>Events</strong> screen — a list of active
            events (bars). Tap an event to open the POS order screen.
        </p>
        <p>
            Back in the management panel, the new device appears in the Devices table with its name and
            online status.
        </p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <img class="img-fluid rounded border" src="/images/screenshot1.png" alt="CatLab Drinks POS order screen showing menu items and order list">
                <small class="text-muted d-block mt-1">The POS order screen: tap menu items to add them to an order.</small>
            </div>
            <div class="col-md-6 mb-3">
                <img class="img-fluid rounded border" src="/images/screenshot2.png" alt="CatLab Drinks POS payment confirmation dialog">
                <small class="text-muted d-block mt-1">The payment dialog: confirm the order total and complete the sale.</small>
            </div>
        </div>
    </div>

    <hr>

    {{-- ================================================================== --}}
    {{-- WHAT HAPPENS UNDER THE HOOD --}}
    {{-- ================================================================== --}}
    <h2>What happens under the hood</h2>
    <p>
        Understanding the technical flow is useful for troubleshooting. Here is what happens at each step:
    </p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 30%">Action</th>
                <th>Technical detail</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Admin clicks <strong>＋</strong></td>
                <td>
                    The management panel calls <code>POST /api/v1/device-connect-requests</code>.
                    The server creates a <code>DeviceConnectRequest</code> record with a unique token
                    and returns a base64-encoded JSON payload containing <code>{ api, token }</code>.
                    This payload is rendered both as a QR code and as a copyable URL.
                </td>
            </tr>
            <tr>
                <td>2</td>
                <td>Device scans QR / pastes URL</td>
                <td>
                    The Android app decodes the payload, extracts the API base URL and token, and calls
                    <code>POST /api/v1/device-connect</code> with <code>{ token, device_uid }</code>.
                    If this is a new device, the server generates a random 6-digit pairing code and returns it.
                </td>
            </tr>
            <tr>
                <td>3</td>
                <td>Admin enters pairing code</td>
                <td>
                    The management panel calls <code>POST /api/v1/device-connect-requests/{token}/pair</code>
                    with the pairing code and a device name. The server validates the code, creates a
                    <code>Device</code> record, and generates a long-lived access token.
                </td>
            </tr>
            <tr>
                <td>4</td>
                <td>Device receives token</td>
                <td>
                    The app polls <code>POST /api/v1/device-connect</code> every second. Once pairing
                    is confirmed, the response contains the access token. The app stores it in local
                    storage and reloads. All subsequent API calls use the
                    <code>Authorization: Bearer {token}</code> header against the <code>/pos-api/v1/</code>
                    endpoints.
                </td>
            </tr>
        </tbody>
    </table>

    <hr>

    {{-- ================================================================== --}}
    {{-- AFTER PAIRING --}}
    {{-- ================================================================== --}}
    <h2>After pairing</h2>

    <h4>Managing devices</h4>
    <p>
        From the <strong>Devices</strong> page you can rename devices, see which ones are online,
        monitor pending orders, and delete devices that are no longer in use. Deleting a device
        revokes its access token — the app will return to the pairing screen the next time it
        tries to reach the server.
    </p>

    <h4>NFC card signing</h4>
    <p>
        If your POS devices use NFC cards for cashless payment, each device generates a cryptographic
        key pair on first use. The public key must be <strong>approved</strong> by an administrator
        in the Devices table before the device can sign cards. This prevents unauthorised terminals
        from creating or modifying card balances.
    </p>

    <h4>Licensing</h4>
    <p>
        Devices can optionally be licensed. From the Devices page you can buy or manually enter a
        license key for each terminal.
    </p>

    <hr>

    {{-- ================================================================== --}}
    {{-- TROUBLESHOOTING --}}
    {{-- ================================================================== --}}
    <h2>Troubleshooting</h2>

    <div class="accordion mb-4" id="troubleshooting">
        <div class="card">
            <div class="card-header" id="faq1">
                <h5 class="mb-0">The app shows the pairing screen again after it was already paired</h5>
            </div>
            <div class="card-body">
                The access token has expired or was revoked (e.g. because the device was deleted in the
                management panel). When the app receives a <strong>401 Unauthorized</strong> response, it
                automatically clears its stored credentials and returns to the Connect Device screen.
                Simply re-pair the device by following the steps above.
            </div>
        </div>

        <div class="card">
            <div class="card-header" id="faq2">
                <h5 class="mb-0">The QR code does not scan</h5>
            </div>
            <div class="card-body">
                Make sure your management panel is accessible from the Android device. If your server
                runs on <code>localhost</code> or a private IP address, the Android device must be on the
                same network. You can also copy the <strong>connection URL</strong> from the modal and
                paste it using the <em>Enter Token Manually</em> option.
            </div>
        </div>

        <div class="card">
            <div class="card-header" id="faq3">
                <h5 class="mb-0">The pairing code is rejected</h5>
            </div>
            <div class="card-body">
                Each connect request is single-use and expires after a short time. If the code is
                rejected, close the dialog, click <strong>＋</strong> again to generate a fresh request,
                and have the device scan the new QR code.
            </div>
        </div>

        <div class="card">
            <div class="card-header" id="faq4">
                <h5 class="mb-0">The device shows "Offline" in the management panel</h5>
            </div>
            <div class="card-body">
                The device status updates every 10 seconds. If the device is online but shows as
                offline, check that the Android device has a working network connection to the
                CatLab Drinks server.
            </div>
        </div>
    </div>

</main>
</body>
</html>
