<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Security whitepaper: How CatLab Drinks securely processes festival payments using standard €0.10 NFC tags. ECDSA per-device signing, replay attack prevention, and offline operation.">
    <meta name="keywords" content="NFC payment security, festival cashless security, ECDSA NFC card signing, NTAG213 security, cashless payment whitepaper, DIY festival payment infrastructure">
    <link rel="canonical" href="{{ url('/whitepaper') }}">

    <title>Security Whitepaper — How to Securely Process Festival Payments Using Standard NFC Tags — CatLab Drinks</title>

    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet" type="text/css">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @include('blocks.favicon')

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

<main class="container my-4">

    <h1>Security Whitepaper</h1>
    <p class="lead">How to Securely Process Festival Payments Using Standard €0.10 NFC Tags</p>

    <hr>

    <h2>1. Executive Summary</h2>
    <p>
        CatLab Drinks is a cashless point-of-sale (POS) system designed for events, festivals, and bars. It uses
        NFC-enabled cards (NTAG213) as physical payment tokens that store a digital balance. Multiple POS terminals
        operate concurrently, with support for offline operation when internet connectivity is unreliable.
    </p>
    <p>
        This whitepaper describes the security architecture and measures taken to protect the integrity of the
        cashless payment ecosystem against fraud, replay attacks, and unauthorized modifications.
    </p>

    <hr>

    <h2>2. Threat Model</h2>

    <h3>2.1 Primary Threats</h3>
    <table class="table table-bordered">
        <thead class="thead-light">
            <tr><th>Threat</th><th>Description</th><th>Risk Level</th></tr>
        </thead>
        <tbody>
            <tr><td><strong>Balance forgery</strong></td><td>An attacker writes arbitrary credit to their NFC card</td><td>Critical</td></tr>
            <tr><td><strong>Replay attack</strong></td><td>Copying signed data from one card to another</td><td>High</td></tr>
            <tr><td><strong>Key interception</strong></td><td>An authenticated user extracts the signing key from the browser</td><td>High</td></tr>
            <tr><td><strong>Rogue terminal</strong></td><td>An unauthorized device enters the ecosystem and signs cards</td><td>High</td></tr>
            <tr><td><strong>Offline tampering</strong></td><td>Exploiting offline POS terminals to manipulate balances</td><td>Medium</td></tr>
            <tr><td><strong>Write interruption</strong></td><td>Power loss during NFC write corrupts card data</td><td>Medium</td></tr>
        </tbody>
    </table>

    <h3>2.2 Previous Vulnerability (Version 0)</h3>
    <p>
        The original system used a single shared symmetric HMAC-SHA256 key across all POS terminals. This key
        was loaded client-side whenever any user logged into the management website, creating a critical vulnerability:
        any authenticated user could intercept the key and forge card balances.
    </p>

    <hr>

    <h2>3. Cryptographic Architecture (Version 1)</h2>

    <h3>3.1 Asymmetric Key Model</h3>
    <p>
        Version 1 replaces the shared symmetric key with <strong>per-device ECDSA P-192 key pairs</strong>:
    </p>
    <ul>
        <li><strong>Each POS terminal</strong> generates and stores its own unique private key</li>
        <li><strong>Public keys</strong> are managed centrally at the organisation (tenant) level</li>
        <li>Terminals <strong>sign</strong> card data with their private key</li>
        <li>Other terminals <strong>verify</strong> signatures using approved public keys downloaded from the server</li>
    </ul>
    <p>
        This eliminates the shared-secret vulnerability: no single key compromise affects the entire system.
    </p>

    <h3>3.2 Why ECDSA P-192?</h3>
    <table class="table table-bordered">
        <thead class="thead-light">
            <tr><th>Criteria</th><th>ECDSA P-192</th><th>ECDSA P-256</th><th>HMAC-SHA256</th></tr>
        </thead>
        <tbody>
            <tr><td>Signature size</td><td>48 bytes</td><td>64 bytes</td><td>32 bytes</td></tr>
            <tr><td>Security level</td><td>96-bit</td><td>128-bit</td><td>256-bit</td></tr>
            <tr><td>Key type</td><td>Asymmetric</td><td>Asymmetric</td><td>Symmetric</td></tr>
            <tr><td>Client-side exposure</td><td>Private key per-device</td><td>Private key per-device</td><td>Shared secret</td></tr>
        </tbody>
    </table>
    <p>
        P-192 was chosen over P-256 to minimize signature size (48 vs 64 bytes), which is important given
        NTAG213's 144-byte memory constraint. The 96-bit security level is more than adequate for a drinks
        credit system where the economic incentive for attacks is low.
    </p>

    <h3>3.3 Key Storage</h3>
    <p>
        The private key is <strong>AES-encrypted</strong> using the device secret (provided by the server via the
        <code>GET /pos-api/v1/devices/current</code> API call) and stored in the browser's localStorage. This means:
    </p>
    <ol>
        <li>The private key can only be decrypted after successful device authentication</li>
        <li>The device secret never leaves the server unencrypted in the management API</li>
        <li>If localStorage is cleared, the key pair is lost and a new one must be generated and approved</li>
        <li>When a new public key is submitted, the server automatically resets the approval status — an administrator must re-approve the new key</li>
        <li>On approval, the device's own public key is registered in its local verification map so it can immediately read cards it signs</li>
    </ol>

    <hr>

    <h2>4. Card Data Integrity</h2>

    <h3>4.1 Signature Scheme</h3>
    <p>The ECDSA P-192 signature covers:</p>
    <pre><code>signature = ECDSA_SIGN(
    SHA-256(version_byte + device_id + card_data + card_hardware_uid),
    device_private_key
)</code></pre>
    <p><strong>Fields included in the signature:</strong></p>
    <ul>
        <li><code>version_byte</code> (1 byte): Prevents version downgrade attacks</li>
        <li><code>device_id</code> (3 bytes): Identifies the signing terminal</li>
        <li><code>card_data</code> (33 bytes): Balance, transaction count, timestamp, previous transactions, discount</li>
        <li><code>card_hardware_uid</code> (variable): The card's unique hardware identifier</li>
    </ul>

    <h3>4.2 Replay Attack Prevention</h3>
    <p>
        The <strong>card hardware UID</strong> is included in the signed data but not stored on the card (it's read from
        the NFC hardware). This prevents an attacker from copying signed data from a high-balance card to a
        low-balance card — the signature verification will fail because the hardware UIDs differ.
    </p>

    <h3>4.3 Version Detection</h3>
    <p>Card data version is determined by the first byte of the payload:</p>
    <ul>
        <li><code>0x01</code> → Version 1 (ECDSA asymmetric)</li>
        <li>Any other value → Version 0 (legacy HMAC-SHA256)</li>
    </ul>

    <hr>

    <h2>5. Key Management &amp; Admin Workflow</h2>

    <h3>5.1 Key Generation</h3>
    <p>
        Key generation is an <strong>explicit manual action</strong> — it is never automatic. The POS terminal shows a modal
        requiring the user to press "Generate Credentials". This ensures operators are aware that a new key pair will
        be created and requires administrator approval.
    </p>

    <h3>5.2 Key Approval Flow</h3>
    <ol>
        <li>POS terminal generates key pair locally</li>
        <li>Public key is uploaded to the server</li>
        <li>Key enters "Pending" state — card operations blocked</li>
        <li>Administrator approves the key via the admin dashboard</li>
        <li>Terminal downloads approved keys — card operations now allowed</li>
    </ol>

    <h3>5.3 Key Revocation</h3>
    <p>
        Administrators can <strong>instantly revoke</strong> a key if a terminal is compromised. The admin dashboard warns
        that revocation is a destructive action because all cards last signed by that device will fail signature verification
        and must be re-scanned at an approved terminal.
    </p>

    <h3>5.4 Device Soft-Delete</h3>
    <p>
        Deleting a POS device soft-deletes it, preserving the public key record and signed card tracking. This ensures
        cards signed by the deleted device can still be verified, and the admin can still manage the device's key.
    </p>

    <hr>

    <h2>6. Offline Operation</h2>

    <h3>6.1 Transaction Splitting</h3>
    <p>
        Cards store the last 5 previous transaction amounts. When a POS terminal goes offline, these stored
        transactions allow the system to reconstruct missing transaction history when connectivity is restored.
    </p>

    <h3>6.2 Transaction Merger</h3>
    <p>
        The <code>TransactionMerger</code> handles reconciliation of transactions received from different terminals
        in potentially different order. It uses database-level locking to prevent race conditions and maintains an
        "overflow" transaction to absorb balance discrepancies.
    </p>

    <hr>

    <h2>7. Physical Security</h2>

    <h3>7.1 NFC Write Protection</h3>
    <p>
        The <a href="https://github.com/catlab-drinks/nfc-socketio">nfc-socketio</a> service applies a write password
        to the NFC tags, derived from the organisation secret and card UID. While NTAG213's 4-byte password is
        insufficient for cryptographic security, it prevents accidental overwrites.
    </p>

    <h3>7.2 NTAG213 Memory Layout</h3>
    <table class="table table-bordered">
        <thead class="thead-light">
            <tr><th>Component</th><th>Size</th></tr>
        </thead>
        <tbody>
            <tr><td>NTAG213 user memory</td><td>144 bytes</td></tr>
            <tr><td>TLV overhead</td><td>3 bytes</td></tr>
            <tr><td>Max NDEF message</td><td>141 bytes</td></tr>
            <tr><td>URI record (topup URL)</td><td>~37 bytes</td></tr>
            <tr><td>External record (v1 data)</td><td>~104 bytes</td></tr>
        </tbody>
    </table>

    <hr>

    <h2>8. Defence in Depth</h2>
    <p>
        The system employs multiple layers of security that must all be defeated for a successful attack:
    </p>
    <ol>
        <li><strong>NFC write password</strong> — prevents casual overwrites (low security, 4 bytes)</li>
        <li><strong>ECDSA signature</strong> — prevents balance forgery without the private key (high security, 96-bit)</li>
        <li><strong>Hardware UID binding</strong> — prevents cross-card replay attacks</li>
        <li><strong>Admin key approval</strong> — prevents rogue terminal injection</li>
        <li><strong>Server reconciliation</strong> — detects anomalies after the fact</li>
        <li><strong>Monotonic counter</strong> — detects rollback/replay of stale card states</li>
    </ol>

    <hr>

    <h2>9. Summary of Security Measures</h2>
    <table class="table table-bordered">
        <thead class="thead-light">
            <tr><th>Measure</th><th>Protection Against</th></tr>
        </thead>
        <tbody>
            <tr><td>Per-device ECDSA key pairs</td><td>Key interception, shared secret compromise</td></tr>
            <tr><td>Card hardware UID in signature</td><td>Replay attacks across cards</td></tr>
            <tr><td>Admin key approval</td><td>Rogue terminals</td></tr>
            <tr><td>Key revocation with impact tracking</td><td>Compromised terminals</td></tr>
            <tr><td>Monotonic transaction counter</td><td>Card state rollback/replay</td></tr>
            <tr><td>Server-side balance reconciliation</td><td>Undetected balance manipulation</td></tr>
            <tr><td>Maximum balance enforcement</td><td>Unrealistic balance forgery</td></tr>
            <tr><td>Version byte in signature</td><td>Version downgrade attacks</td></tr>
            <tr><td>NFC write password</td><td>Accidental overwrites</td></tr>
            <tr><td>Offline transaction recovery</td><td>Data loss from connectivity issues</td></tr>
        </tbody>
    </table>

    <hr>

    <div class="row mb-4">
        <div class="col-md-12">
            <p>
                <a href="/">← Back to CatLab Drinks homepage</a> ·
                <a href="https://github.com/CatLabInteractive/catlab-drinks">View source code on GitHub</a>
            </p>
        </div>
    </div>

</main>

</body>
</html>
