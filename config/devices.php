<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Display Grace Period
	|--------------------------------------------------------------------------
	|
	| The number of seconds after a device's last ping before it is shown
	| as offline in the management interface.
	|
	*/
	'display_grace_period' => env('DEVICE_DISPLAY_GRACE_PERIOD', 60),

	/*
	|--------------------------------------------------------------------------
	| Reassignment Grace Period
	|--------------------------------------------------------------------------
	|
	| The number of seconds after a device's last ping before its pending
	| orders are reassigned to other online devices.
	|
	*/
	'reassignment_grace_period' => env('DEVICE_REASSIGNMENT_GRACE_PERIOD', 300),

];
