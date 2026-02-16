<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Connect Device — CatLab Drinks</title>

    @include('blocks.favicon')

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(-60deg, #c4d1d1, #e7dede);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .connect-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
            padding: 40px;
            max-width: 440px;
            width: 100%;
            text-align: center;
        }
        .connect-card h1 {
            font-size: 1.6rem;
            color: #333;
            margin-bottom: 8px;
        }
        .connect-card .subtitle {
            color: #888;
            margin-bottom: 32px;
        }
        .connect-card .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 12px;
            transition: opacity .15s;
        }
        .connect-card .btn:hover { opacity: .85; }
        .btn-playstore {
            background: #01875f;
            color: #fff;
        }
        .btn-browser {
            background: #3490dc;
            color: #fff;
        }
        .connect-card .separator {
            color: #aaa;
            margin: 8px 0;
            font-size: .9rem;
        }
        .connect-card .error {
            background: #f8d7da;
            color: #721c24;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="connect-card">
    <h1>🍹 CatLab Drinks</h1>
    <p class="subtitle">Connect a POS device</p>

    @if(isset($error))
        <div class="error">{{ $error }}</div>
    @endif

    @if(isset($connectData))
        <a class="btn btn-playstore"
           href="https://play.google.com/store/apps/details?id=eu.catlab.drinks">
            📱 Open in Android App
        </a>

        <p class="separator">— or —</p>

        <a class="btn btn-browser"
           href="{{ $posUrl }}">
            🌐 Continue in Browser
        </a>
    @else
        <div class="error">No connection data provided. Please scan a valid QR code.</div>
    @endif
</div>

</body>
</html>
