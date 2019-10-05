@extends('layouts.order')

@section('content')
    <div class="container">

        <!--<p class="alert alert-danger">Online herladen is nog niet beschikbaar.</p>-->
        <h2>Kaart opladen</h2>

        @if($topup->isCancelled())
            <div class="alert alert-danger">
                De betaling is mislukt.<br />
                <a href="{{ $retryUrl }}" class="btn btn-danger btn-sm">Probeer het opnieuw</a>
            </div>
        @elseif($topup->isPending())
            <div class="alert alert-warning">
                We hebben nog geen bevestiging van je betaling.
            </div>
        @elseif($topup->isSuccess())
            <div class="alert alert-success">
                De betaling is gelukt, je kaart is voor {{ $topup->amount }} herladen.
            </div>
        @endif

    </div>
@endsection
