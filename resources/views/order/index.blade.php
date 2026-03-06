@extends('layouts.order')

@section('head')
    <base href="{{ $baseUrl }}" />
@endsection

@section('content')
    <app></app>
@endsection

@section('footer')
    <script type="text/javascript">
        var BASE_URL = '{{$baseUrl}}';
        var EVENT_TOKEN = '{{$token }}';
        var ORDER_SIGNATURE = '{{ $signature }}';
        var ORDER_CARD_TOKEN = '{{ $cardToken }}';
        var ORDER_NAME = '{{ $orderName }}';
    </script>
@endsection
