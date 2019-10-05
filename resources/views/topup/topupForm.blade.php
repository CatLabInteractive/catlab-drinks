@extends('layouts.order')

@section('content')
    <div class="container">


        <!--<p class="alert alert-danger">Online herladen is nog niet beschikbaar.</p>-->


        <h2>Kaart opladen</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p>Kies het gewenste bedrag (vanaf €10,00).</p>

        <form class="{{ $action }}" method="post">
            @csrf
            <div class="form-group mb-2">
                <label for="amount" class="sr-only">Bedrag</label>
                <input type="number" class="form-control" id="amount" name="amount" placeholder="Bedrag" min="{{ $minTopup }}" max="{{ $maxTopup }}" step="0.01"  />
            </div>
            <button type="submit" class="btn btn-primary mb-2">Opladen</button>
        </form>

    </div>
@endsection
