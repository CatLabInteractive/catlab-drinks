@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Registration closed') }}</div>

                <div class="card-body">
                    <p>{{ __('Registration is closed on this instance.') }}</p>
                    <p>
                        {{ __('CatLab Drinks is open source software: you can set up your own instance for free.') }}
                        <a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance">{{ __('See the setup instructions to get started.') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
