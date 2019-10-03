<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, width=device-width, maximum-scale=1.0, user-scalable=0">

    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CatLab Drinks</title>

    <base href="/client/" />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('blocks.favicon')

    <style type="text/css" media="print">
        @page {
            size: auto;
            margin: 0;
        }
    </style>

    <style>
        body{

        }
    </style>

    <style type="text/css">

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 210mm;
        }

        @page {
            size: A4 portrait;
            margin-left: 7.2mm;
            margin-right: 7mm;
            margin-top: 15.2mm;
        }

        #cards {
            width: 195.8mm;
            height: 271.8mm;
            //border: 1px solid yellow;
        }

        .card {
            width: 63.7mm;
            height: 38.1mm;
            //border: 1px dashed gray;
            float: left;
            margin-right: 2mm;
        }

        .card:nth-child(3n) {
            margin-right: 0;
        }

        .card .qr {
            text-align: center;
        }

        .card .qr img {
            margin-top: 1mm;
            //width: 28mm;
            //height: 28mm;
        }

        .card .uid {
            font-size: 9px;
            font-family: sans-serif;
            text-align: center;
            margin-top: -3mm;
        }

    </style>

<body>
<div id="cards">
    <!--
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=043c9aea905b81"></div><div class="uid">043c9aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    <div class="card"><div class="qr"><img src="/qr-generator/code?uid=04419aea905b81"></div><div class="uid">04419aea905b81</div></div>
    -->
</div>
<script src="{{ mix('res/sales/js/qrGenerator.js') }}"></script>
</body>
</html>
