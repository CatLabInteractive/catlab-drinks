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

/**
 * CatLab's defaults for the opt-in usage and error reporting.
 * The values here are only used when an instance opts in; an instance can
 * override every one of them with its own environment variables.
 *
 * This class is read from config files, so it may use env().
 */
class Telemetry
{
    // TODO: fill in CatLab's Matomo instance and site.
    public const CATLAB_MATOMO_URL = null;
    public const CATLAB_MATOMO_SITE_ID = null;

    // TODO: fill in CatLab's Errbit / Airbrake project.
    public const CATLAB_AIRBRAKE_HOST = null;
    public const CATLAB_AIRBRAKE_PROJECT_ID = null;
    public const CATLAB_AIRBRAKE_PROJECT_KEY = null;

    /**
     * Request keys that are never sent along with an error report.
     */
    public const AIRBRAKE_KEYS_BLOCKLIST = [
        '/password/i', '/secret/i', '/token/i', '/authorization/i', '/cookie/i',
        '/email/i', '/key/i', '/signature/i', '/card/i',
    ];

    /**
     * The Airbrake project errors go to: the instance's own AIRBRAKE_* project
     * when set, otherwise CatLab's project when ERROR_REPORTING is on,
     * otherwise none (null project id disables Airbrake).
     *
     * @return array{projectId: mixed, projectKey: mixed, host: mixed, keysBlocklist: ?array}
     */
    public static function airbrakeProject(): array
    {
        if (env('AIRBRAKE_PROJECT_ID')) {
            return [
                'projectId' => env('AIRBRAKE_PROJECT_ID'),
                'projectKey' => env('AIRBRAKE_PROJECT_KEY'),
                'host' => env('AIRBRAKE_HOST'),
                'keysBlocklist' => null,
            ];
        }

        if (env('ERROR_REPORTING') && static::CATLAB_AIRBRAKE_PROJECT_ID) {
            return [
                'projectId' => static::CATLAB_AIRBRAKE_PROJECT_ID,
                'projectKey' => static::CATLAB_AIRBRAKE_PROJECT_KEY,
                'host' => static::CATLAB_AIRBRAKE_HOST,
                'keysBlocklist' => static::AIRBRAKE_KEYS_BLOCKLIST,
            ];
        }

        return [
            'projectId' => null,
            'projectKey' => null,
            'host' => null,
            'keysBlocklist' => null,
        ];
    }
}
