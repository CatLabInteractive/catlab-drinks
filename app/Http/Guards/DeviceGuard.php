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
		// Resolve the current request instead of the one captured at construction,
		// so a long-lived container (tests, octane) doesn't reuse a stale token.
		$request = app()->bound('request') ? app('request') : $this->request;

		$accessToken = $request->bearerToken();
		if (!$accessToken) {
			return false;
		}

		$token = DeviceAccessToken::findValid($accessToken);
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