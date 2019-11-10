<script>
var AIRBRAKE_CONFIG = null;
@if(config('airbrake.projectId'))
    AIRBRAKE_CONFIG = {!! json_encode([
            'projectId' => config('airbrake.projectId'),
            'projectKey' => config('airbrake.projectKey'),
            'environment' => config('airbrake.environment'),
            'host' => config('airbrake.host')
        ]) !!}
@endif
</script>
