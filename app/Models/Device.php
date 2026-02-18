<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Device extends Model implements
    AuthenticatableContract,
    AuthorizableContract
{
    use Authenticatable, Authorizable;

	protected $fillable = [
		'uid',
		'name',
		'secret_key',
	];

	protected static function booted()
	{
		static::deleting(function (Device $device) {
			$device->accessTokens()->delete();
			$device->connectRequests()->delete();
		});
	}

	/**
	 * @return string
	 */
	public static function generateUid() : string
	{
		$uuid = Str::uuid();

		// Check for duplicates (YES I KNOW THIS IS SILLY). Leave me be.
		while (Device::where('uid', $uuid)->count() > 0) {
			$uuid = Str::uuid();
		}

		return $uuid;
	}

	/**
	 * Get a device from a UID and create if it doesn't exist.
	 */
	public static function getFromUid(
		string $uid,
		Organisation $organisation,
		?string $deviceName = null
	) : Device
	{
		// Check if this device is registered to this organisation.
		$device = Device::where('uid', $uid)
			->where('organisation_id', $organisation->id)
			->first();

		if (!$device) {
			// Create a new device.
			$device = new Device();
			$device->uid = $uid;
			$device->name = $deviceName;
			$device->organisation()->associate($organisation);

			$device->secret_key = Crypt::encryptString(Str::random(16));

			$device->save();
		}

		return $device;
	}

	/**
	 * @return BelongsTo
	 */
	public function organisation()
	{
		return $this->belongsTo(Organisation::class);
	}

	/**
	 * @return HasMany
	 */
	public function accessTokens()
	{
		return $this->hasMany(DeviceAccessToken::class);
	}

	/**
	 * @return HasMany
	 */
	public function connectRequests()
	{
		return $this->hasMany(DeviceConnectRequest::class);
	}

	/**
	 * @return DeviceAccessToken
	 */
	public function createAccessToken(User $user, ?int $lifetimeSeconds = null)
	{
		// Revoke all existing tokens.
		$this->accessTokens()->delete();
		$this->connectRequests()->delete();

		if (!$lifetimeSeconds) {
			$lifetimeSeconds = 60 * 60 * 24 * 7; // 7 days
		}

		$accessToken = new DeviceAccessToken();
		$accessToken->device()->associate($this);
		$accessToken->createdBy()->associate($user);
		$accessToken->access_token = Str::random(64);
		$accessToken->expires_at = now()->addSeconds($lifetimeSeconds);

		$accessToken->save();
		return $accessToken;
	}
}
