<?php

namespace App\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Class DeviceConnectRequest
 * @package App\Models
 */
class DeviceConnectRequest extends Model
{
	public const STATE_PENDING = 'pending';
	public const STATE_ACCEPTED = 'accepted';
	public const STATE_REQUIRES_PAIRING_CODE = 'requires_pairing_code';

	/**
	 * @return BelongsTo<Organisation>
	 */
	public function organisation()
	{
		return $this->belongsTo(Organisation::class);
	}

	/**
	 * @return BelongsTo<User>
	 */
	public function createdBy()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	/**
	 * @return string
	 */
	public function getState() : string {

		if ($this->device_id) {
			return self::STATE_ACCEPTED;
		}

		if ($this->pairing_code) {
			return self::STATE_REQUIRES_PAIRING_CODE;
		}

		return self::STATE_PENDING;

	}

	/**
	 *
	 * @param string $code
	 * @param string $deviceName
	 * @return true
	 */
	public function verifyPairingCode(string $code, string $deviceName)
	{
		if ($this->pairing_code !== $code) {
			throw new LogicException('Invalid pairing code.');
		}

		$device = Device::getFromUid($this->device_uid, $this->organisation, $deviceName);
		$device->save();

		$this->device_id = $device->id;
		$this->pairing_code_accepted = true;
		$this->save();

		return true;
	}

	/**
	 * Process a claim.
	 * @param DeviceConnectClaim $claim
	 * @return Device | null
	 */
	public function processClaim(DeviceConnectClaim $claim): Device | null
	{
		// Do we have a registered device?

		/** @var Organisation $organisation */
		$organisation = $this->organisation;

		if (!$claim->device_uid) {

			if ($this->device_uid)  {
				// We have a device set in this request, but none in the claim.
				throw new LogicException('Connect request is already claimed.');
			}

			// This is a new device, we need to set a random device UID.
			$claim->device_uid = Device::generateUid();

			// We also need to set a pairing code as the device needs to be accepted.
			$this->device_uid = $claim->device_uid;
			$this->generatePairingCode();
			$claim->pairing_code = $this->pairing_code;

			$this->save();

			return null;

		}

		// Claim has a device_uid; check if it matches any in our database

		// Do we have one set in the request already?
		if ($this->device_uid) {

			// We already have a device set in this request.
			// Check if it matches our input.
			if ($this->device_uid !== $claim->device_uid) {
				throw new LogicException('Device UID does not match.');
			}

			$claim->pairing_code = $this->pairing_code;

			// Has pairing process been completed?
			if ($this->pairing_code_accepted) {

				// Awesome! Let's claim.
				return $this->acceptClaim($claim);

			}

			// Waiting for pairing code to be accepted.
			return null;
		}

		// Request has not been claimed yet; check if the provided device uid is
		// already registered to this organisation.
		$device = Device::firstOrNew([ 'uid' => $claim->device_uid ]);

		// Now that is out of the way, check if this device is registered to this organisation.
		$device = Device::where('uid', $claim->device_uid)
			->where('organisation_id', $organisation->id)
			->first();

		if ($device) {
			// This is a known device, no pairing required.
			return $this->acceptClaim($claim);
		}

		// Existing, but unrecognized device.
		// We need to set a pairing code.
		$this->device_uid = $claim->device_uid;
		$this->generatePairingCode();
		$claim->pairing_code = $this->pairing_code;
		$this->save();

		return null;
	}

	/**
	 * Get the URL for this request.
	 * @return string
	 * @throws BindingResolutionException
	 */
	public function getUrl(): string
	{
		return base64_encode(json_encode([
			'api' => url('/'),
			'token' => $this->token
		]));
	}

	/**
	 * Accept a claim.
	 * @param DeviceConnectClaim $claim
	 * @return Device
	 */
	protected function acceptClaim(DeviceConnectClaim $claim): Device
	{
		$device = Device::getFromUid($claim->device_uid, $this->organisation);

		$this->device_id = $device->id;
		$this->accepted_at = \Carbon\Carbon::now();

		$this->save();

		return $device;
	}

	/**
	 * @return void
	 */
	protected function generatePairingCode(): void
	{
		$this->pairing_code = mt_rand(100000, 999999);
	}
}
