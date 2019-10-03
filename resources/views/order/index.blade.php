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
    </script>
@endsection
