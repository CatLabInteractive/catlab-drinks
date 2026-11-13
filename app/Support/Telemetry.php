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
 * CatLab's defaults for the usage and error reporting. Both are on unless
 * the instance opts out (USAGE_REPORTING=false / ERROR_REPORTING=false); an
 * instance can override every destination with its own environment variables.
 *
 * isOn() and airbrakeProject() are read from config files, so they may use
 * env(). Everything else must read config().
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
     * Environments that do not report unless the switch is set explicitly, so
     * development machines and test runs stay out of CatLab's statistics.
     */
    public const QUIET_ENVIRONMENTS = ['local', 'testing'];

    /**
     * Is the USAGE_REPORTING / ERROR_REPORTING switch on? Unset means on,
     * except in the quiet environments. "false", "0", "off", "no" and an
     * empty value turn it off.
     */
    public static function isOn(string $switch): bool
    {
        $default = !in_array(env('APP_ENV', 'production'), static::QUIET_ENVIRONMENTS, true);

        return filter_var(env($switch, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * What this instance reports to CatLab right now, for the notice in the
     * page footers. Reads config(), so it also works with a cached config.
     *
     * @return array{usage: bool, errors: bool}
     */
    public static function notice(): array
    {
        return [
            'usage' => config('telemetry.usage.enabled')
                && config('telemetry.usage.matomo_url')
                && config('telemetry.usage.matomo_site_id'),
            'errors' => (bool) config('telemetry.errors.to_catlab'),
        ];
    }

    /**
     * The Airbrake project errors go to: the instance's own AIRBRAKE_* project
     * when set, otherwise CatLab's project unless ERROR_REPORTING is off,
     * otherwise none (null project id disables Airbrake).
     *
     * @return array{projectId: mixed, projectKey: mixed, host: mixed, keysBlocklist: ?array, catlab: bool}
     */
    public static function airbrakeProject(): array
    {
        if (env('AIRBRAKE_PROJECT_ID')) {
            return [
                'projectId' => env('AIRBRAKE_PROJECT_ID'),
                'projectKey' => env('AIRBRAKE_PROJECT_KEY'),
                'host' => env('AIRBRAKE_HOST'),
                'keysBlocklist' => null,
                'catlab' => false,
            ];
        }

        if (static::isOn('ERROR_REPORTING') && static::CATLAB_AIRBRAKE_PROJECT_ID) {
            return [
                'projectId' => static::CATLAB_AIRBRAKE_PROJECT_ID,
                'projectKey' => static::CATLAB_AIRBRAKE_PROJECT_KEY,
                'host' => static::CATLAB_AIRBRAKE_HOST,
                'keysBlocklist' => static::AIRBRAKE_KEYS_BLOCKLIST,
                'catlab' => true,
            ];
        }

        return [
            'projectId' => null,
            'projectKey' => null,
            'host' => null,
            'keysBlocklist' => null,
            'catlab' => false,
        ];
    }
}
