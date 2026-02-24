<?php

namespace App\Http\Guards;

use App\Models\DeviceAccessToken;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use LogicException;

class DeviceGuard implements Guard
{
	use GuardHelpers;

	private \Illuminate\Http\Request $request;

	public function __construct(
		\Illuminate\Http\Request $request
	) {
		$this->request = $request;
	}

	public function user()
	{
		return $this->user;
	}

	public function check()
	{
		$accessToken = $this->request->bearerToken();
		
		$token = DeviceAccessToken::where('access_token', $accessToken)
			->where('expires_at', '>', now())
			->first();

		if (!$token) {
			return false;
		}

		$this->user = $token->device;

		// Update last_ping on every authenticated request
		$this->user->touchLastPing();

		return true;
	}

	/**
	 * @return bool
	 */
	public function validate(array $credentials = [])
	{
		throw new LogicException('Not implemented');
	}
}