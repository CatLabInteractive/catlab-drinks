<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Device extends Model implements
    AuthenticatableContract,
    AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, SoftDeletes;

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
		'approved_at' => 'datetime',
	];

	/**
	 * Maximum device ID that fits in 3 bytes (unsigned).
	 * Used for compact device ID encoding in NFC card v1 format.
	 */
	const MAX_DEVICE_ID = 16777215; // 0xFFFFFF

	protected static function booted()
	{
		static::creating(function (Device $device) {
			// Validate that the auto-increment ID will fit in 3 bytes.
			// Since we can't know the exact ID before creation, check the current max.
			$maxId = Device::withTrashed()->max('id') ?? 0;
			if ($maxId >= self::MAX_DEVICE_ID) {
				throw new \RuntimeException(
					'Cannot create more devices: next ID would exceed the 3-byte unsigned integer limit (' . self::MAX_DEVICE_ID . ').'
				);
			}
		});

		static::deleting(function (Device $device) {
			if ($device->isForceDeleting()) {
				$device->accessTokens()->delete();
				$device->connectRequests()->delete();
			} else {
				// Soft delete: revoke access tokens but keep public key
				$device->accessTokens()->delete();
			}
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
	 * Get all cards that were last signed by this device.
	 * @return HasMany
	 */
	public function signedCards()
	{
		return $this->hasMany(Card::class, 'last_signing_device_id');
	}

	/**
	 * Get the count of cards last signed by this device.
	 * @return int
	 */
	public function getSignedCardsCountAttribute(): int
	{
		return $this->signedCards()->count();
	}

	/**
	 * Check if this device's public key has been approved.
	 * @return bool
	 */
	public function isApproved(): bool
	{
		return $this->approved_at !== null;
	}

	/**
	 * Approve this device's public key.
	 * @param User $approver
	 * @return void
	 */
	public function approvePublicKey(User $approver): void
	{
		$this->approved_at = Carbon::now();
		$this->approved_by = $approver->id;
		$this->save();
	}

	/**
	 * Revoke this device's public key approval.
	 * @return void
	 */
	public function revokePublicKey(): void
	{
		$this->public_key = null;
		$this->approved_at = null;
		$this->approved_by = null;
		$this->save();
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
