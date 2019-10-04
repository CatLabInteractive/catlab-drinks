@extends('layouts.order')

@section('content')
    <div class="container">


        <!--<p class="alert alert-danger">Online herladen is nog niet beschikbaar.</p>-->


        <h2>Kaart opladen</h2>
        <p>Kies het gewenste bedrag (vanaf €10,00).</p>

        <form class="">
            <div class="form-group mb-2">
                <label for="amount" class="sr-only">Bedrag</label>
                <input type="number" class="form-control" id="amount" placeholder="Bedrag" min="10" step="0.01" />
            </div>
            <button type="submit" class="btn btn-primary mb-2">Opladen</button>
        </form>

    </div>
@endsection
