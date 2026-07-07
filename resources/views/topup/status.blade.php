@extends('layouts.order')

@section('content')
    <div class="container">

        <!--<p class="alert alert-danger">{{ __('topup.not_available') }}</p>-->
        <h2>{{ __('topup.title') }}</h2>

        @if($topup->isCancelled())
            <div class="alert alert-danger">
                {{ __('topup.payment_failed') }}<br />
                <a href="{{ $retryUrl }}" class="btn btn-danger btn-sm">{{ __('topup.retry') }}</a>
            </div>
        @elseif($topup->isPending())
            <div class="alert alert-success">
                {{ __('topup.payment_pending') }}
            </div>
        @elseif($topup->isSuccess())
            <div class="alert alert-success">
                {{ __('topup.payment_success', ['amount' => $topup->amount]) }}
            </div>
        @endif

    </div>
@endsection
