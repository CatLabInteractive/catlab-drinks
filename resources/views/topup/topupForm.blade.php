@extends('layouts.order')

@section('content')
    <div class="container">


        <!--<p class="alert alert-danger">{{ __('topup.not_available') }}</p>-->

        <h2>{{ __('topup.current_balance') }}</h2>
        <p>
            € {{ $balance }}
        </p>

        <h2>{{ __('topup.title') }}</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p>{{ __('topup.choose_amount', ['min' => $minTopup]) }}</p>

        <form class="{{ $action }}" method="post">
            @csrf
            <div class="form-group mb-2">
                <label for="amount" class="sr-only">{{ __('topup.amount_label') }}</label>
                <input type="number" class="form-control" id="amount" name="amount" placeholder="{{ __('topup.amount_placeholder') }}" min="{{ $minTopup }}" max="{{ $maxTopup }}" step="0.01"  />
            </div>
            <button type="submit" class="btn btn-primary mb-2">{{ __('topup.submit') }}</button>
        </form>

    </div>
@endsection
