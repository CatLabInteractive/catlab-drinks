<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Offline Grace Period
	|--------------------------------------------------------------------------
	|
	| The number of seconds after a device's last ping before it is considered
	| offline and its pending orders are reassigned to other devices.
	|
	*/
	'offline_grace_period' => env('DEVICE_OFFLINE_GRACE_PERIOD', 300),

];
