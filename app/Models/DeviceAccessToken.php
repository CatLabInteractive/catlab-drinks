<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class DeviceAccessToken
 * @package App\Models
 */
class DeviceAccessToken extends Model
{
	protected $table = 'device_access_tokens';

	/**
	 * The unhashed token, only available right after creation.
	 * @var string|null
	 */
	public ?string $plainTextToken = null;

	protected $fillable = [
		'device_id',
		'access_token',
		'expires_at'
	];

	/**
	 * Tokens are stored hashed. Tokens issued before hashing was introduced are
	 * still stored in plain text; they are accepted once and upgraded in place.
	 *
	 * @param string $plainToken
	 * @return DeviceAccessToken|null
	 */
	public static function findValid(string $plainToken): ?DeviceAccessToken
	{
		$valid = static::where('expires_at', '>', now());

		$token = (clone $valid)->where('access_token', self::hash($plainToken))->first();
		if ($token) {
			return $token;
		}

		// Legacy tokens were random alphanumeric strings; anything shaped like a
		// stored hash is never looked up in plain text, so a leaked hash can't be
		// used as a token.
		if (self::looksLikeHash($plainToken)) {
			return null;
		}

		$token = (clone $valid)->where('access_token', $plainToken)->first();
		if ($token) {
			$token->access_token = self::hash($plainToken);
			$token->save();
		}

		return $token;
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	public static function looksLikeHash(string $value): bool
	{
		return preg_match('/^[0-9a-f]{64}$/', $value) === 1;
	}

	/**
	 * @param string $plainToken
	 * @return string
	 */
	public static function hash(string $plainToken): string
	{
		return hash('sha256', $plainToken);
	}

	/**
	 * @return BelongsTo<Device>
	 */
	public function device()
	{
		return $this->belongsTo(Device::class);
	}

	/**
	 * @return BelongsTo<User>
	 */
	public function createdBy()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}