@php($telemetryNotice = \App\Support\Telemetry::notice())
@if($telemetryNotice['usage'] || $telemetryNotice['errors'])
    <footer class="container text-center text-muted small py-3" id="telemetry-notice">
        @if($telemetryNotice['usage'] && $telemetryNotice['errors'])
            {{ __('This instance sends anonymous usage statistics and error reports to CatLab.') }}
        @elseif($telemetryNotice['usage'])
            {{ __('This instance sends anonymous usage statistics to CatLab.') }}
        @else
            {{ __('This instance sends error reports to CatLab.') }}
        @endif
        <a href="https://github.com/CatLabInteractive/catlab-drinks#usage-and-error-reporting" target="_blank" rel="noopener">{{ __('What is sent and how to turn it off') }}</a>
    </footer>
@endif
