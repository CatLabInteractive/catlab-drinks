<?php

namespace App\Models;

use Carbon\Carbon;
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
		'category_filter_id',
		'allow_remote_orders',
		'allow_live_orders',
	];

	protected $casts = [
		'last_ping' => 'datetime',
		'last_activity' => 'datetime',
		'allow_remote_orders' => 'boolean',
		'allow_live_orders' => 'boolean',
	];

	protected static function booted()
	{
		static::deleting(function (Device $device) {
			$device->accessTokens()->delete();
			$device->connectRequests()->delete();
		});

		static::updated(function (Device $device) {
			// If settings affecting order assignment changed, re-evaluate assignments
			$needsReassignment = $device->wasChanged('category_filter_id')
				|| ($device->wasChanged('allow_remote_orders') && !$device->allow_remote_orders);

			if ($needsReassignment) {
				$assignmentService = new \App\Services\OrderAssignmentService();
				$events = \App\Models\Event::where('organisation_id', $device->organisation_id)->get();
				foreach ($events as $event) {
					$assignmentService->reevaluateAssignments($event, $device);
				}
			}
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
	 * @return BelongsTo
	 */
	public function categoryFilter()
	{
		return $this->belongsTo(Category::class, 'category_filter_id');
	}

	/**
	 * @return HasMany
	 */
	public function assignedOrders()
	{
		return $this->hasMany(Order::class, 'assigned_device_id');
	}

	/**
	 * Check if this device is considered online (for display purposes).
	 * @return bool
	 */
	public function isOnline(): bool
	{
		if (!$this->last_ping) {
			return false;
		}

		$gracePeriod = config('devices.display_grace_period', 60);
		return $this->last_ping->gt(Carbon::now()->subSeconds($gracePeriod));
	}

	/**
	 * Get the count of pending orders assigned to this device.
	 * @return int
	 */
	public function getPendingOrdersCountAttribute(): int
	{
		return $this->assignedOrders()
			->where('status', Order::STATUS_PENDING)
			->count();
	}

	/**
	 * Touch the last_ping timestamp.
	 * @return void
	 */
	public function touchLastPing(): void
	{
		$this->last_ping = Carbon::now();
		$this->saveQuietly();
	}

	/**
	 * @return DeviceAccessToken
	 */
	public function createAccessToken(User $user, ?int $lifetimeSeconds = null)
	{
		// Revoke all existing tokens.
		$this->accessTokens()->delete();

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
