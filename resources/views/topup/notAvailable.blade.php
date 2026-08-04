@extends('layouts.order')

@section('content')
    <div class="container">

        <!--<p class="alert alert-danger">{{ __('topup.not_available') }}</p>-->
        <h2>{{ __('topup.title') }}</h2>
        <div class="alert alert-danger">
            {{ __('topup.not_available') }}
        </div>

    </div>
@endsection
