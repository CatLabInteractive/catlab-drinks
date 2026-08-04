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

namespace App\Support;

use App\Models\User;

/**
 * Instance-level settings: which organisations may use this instance in
 * production, and whether public registration is open.
 */
class InstanceSettings
{
    /**
     * Parse a comma-separated list of numeric IDs into an array of ints.
     * Whitespace is tolerated; non-numeric entries are ignored.
     *
     * @param string|null $value
     * @return int[]
     */
    public static function parseIdList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }

        return $ids;
    }

    /**
     * The first-run setup page is required while no users exist.
     * SSO instances never use the local setup page: their first login
     * through the SSO creates the founding user instead.
     *
     * @return bool
     */
    public static function isSetupRequired(): bool
    {
        if (config('services.catlab.client_id')) {
            return false;
        }

        return User::count() === 0;
    }

    /**
     * Registration is open while no users exist (the founder must be able
     * to register) or when the instance explicitly keeps it open.
     *
     * @return bool
     */
    public static function isRegistrationOpen(): bool
    {
        if (config('instance.registration_open')) {
            return true;
        }

        return User::count() === 0;
    }
}
