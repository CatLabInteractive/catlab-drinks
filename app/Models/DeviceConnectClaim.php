<?php

namespace App\Models;

class DeviceConnectClaim {

	public string $token;

	public ?string $device_uid = null;

	public ?string $pairing_code = null;

	public ?string $access_token = null;

}