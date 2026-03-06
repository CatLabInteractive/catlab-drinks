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
        var ORDER_SIGNATURE = @json($signature);
        var ORDER_CARD_TOKEN = @json($cardToken);
        var ORDER_NAME = @json($orderName);
    </script>
@endsection
