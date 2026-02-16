<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Class OrganisationPaymentGateway
 * @package App\Models
 */
class OrganisationPaymentGateway extends Model
{
    const GATEWAY_PAYNL = 'paynl';

    protected $table = 'organisation_payment_gateways';

    protected $fillable = [
        'gateway',
        'is_testing',
        'is_active',
    ];

    protected $casts = [
        'is_testing' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Set the credentials attribute (encrypts the value).
     * @param array $value
     */
    public function setCredentialsAttribute($value)
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    /**
     * Get the credentials attribute (decrypts the value).
     * @return array
     */
    public function getCredentialsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        return json_decode(Crypt::decryptString($value), true);
    }

    /**
     * Get a specific credential value.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getCredential(string $key, $default = null)
    {
        $credentials = $this->credentials;
        return $credentials[$key] ?? $default;
    }

    /**
     * Get the required credential keys for a given gateway.
     * @param string $gateway
     * @return array
     */
    public static function getRequiredCredentials(string $gateway): array
    {
        return match ($gateway) {
            self::GATEWAY_PAYNL => ['apiToken', 'apiSecret', 'serviceId'],
            default => [],
        };
    }

    /**
     * Check if all required credentials are set.
     * @return bool
     */
    public function hasValidCredentials(): bool
    {
        $required = self::getRequiredCredentials($this->gateway);
        $credentials = $this->credentials;

        foreach ($required as $key) {
            if (empty($credentials[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Accessor for has_valid_credentials attribute (used by Charon).
     * @return bool
     */
    public function getHasValidCredentialsAttribute(): bool
    {
        return $this->hasValidCredentials();
    }
}
