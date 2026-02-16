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

	protected $fillable = [
		'device_id',
		'access_token',
		'expires_at'
	];

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