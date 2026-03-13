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

namespace App\Services;

/**
 * Class OrderTokenSignatureService
 *
 * Handles signing and verification of remote order URL parameters.
 * When third-party applications (like QuizWitz) add query parameters
 * such as `card` or `name` to the remote order URL, these parameters
 * must be signed using the event's order token secret.
 *
 * @package App\Services
 */
class OrderTokenSignatureService
{
    /**
     * Parameters that are included in signature calculation.
     * Only these parameters are signed; all others are ignored.
     */
    const SIGNABLE_PARAMS = ['card', 'name'];

    /**
     * Generate a signature for the given parameters using the provided secret.
     *
     * The signing algorithm:
     * 1. Filter to only include signable parameters (card, name)
     * 2. Sort parameters alphabetically by key
     * 3. Build a query string: key1=value1&key2=value2
     * 4. Compute HMAC-SHA256 using the secret as key
     * 5. Return the hex-encoded signature
     *
     * @param string $secret The order token secret
     * @param array $params Associative array of parameters to sign
     * @return string Hex-encoded HMAC-SHA256 signature
     */
    public static function sign(string $secret, array $params): string
    {
        $signableParams = self::getSignableParams($params);

        if (empty($signableParams)) {
            return '';
        }

        $message = self::buildSignatureMessage($signableParams);

        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * Verify a signature against the provided parameters and secret.
     *
     * @param string $secret The order token secret
     * @param array $params Associative array of parameters that were signed
     * @param string $signature The signature to verify
     * @return bool True if the signature is valid
     */
    public static function verify(string $secret, array $params, string $signature): bool
    {
        $expected = self::sign($secret, $params);
		\Log::info("Verifying signature. Secret: $secret, Params: " . json_encode($params) . ", Expected: $expected, Provided: $signature");

        if (empty($expected)) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    /**
     * Check if the given parameters contain any signable parameters.
     *
     * @param array $params
     * @return bool
     */
    public static function hasSignableParams(array $params): bool
    {
        return !empty(self::getSignableParams($params));
    }

    /**
     * Filter parameters to only include signable ones.
     *
     * @param array $params
     * @return array
     */
    public static function getSignableParams(array $params): array
    {
        $filtered = [];
        foreach (self::SIGNABLE_PARAMS as $key) {
            if (isset($params[$key]) && $params[$key] !== '' && $params[$key] !== null) {
                $filtered[$key] = $params[$key];
            }
        }
        return $filtered;
    }

    /**
     * Build the message string to be signed.
     * Parameters are sorted alphabetically by key and joined as key=value pairs.
     * Values are URL-encoded (RFC 3986) to prevent ambiguity when values contain
     * special characters like & or =.
     *
     * @param array $params
     * @return string
     */
    private static function buildSignatureMessage(array $params): string
    {
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $parts);
    }
}
