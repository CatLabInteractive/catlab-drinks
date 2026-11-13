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

namespace App\Logging;

use Airbrake\Notifier;
use Monolog\Level;
use Monolog\Logger;

/**
 * Factory for the "airbrake" log channel (config/logging.php, driver "custom").
 *
 * Replaces kouz/laravel-airbrake, which only supports Laravel <= 9 and
 * Monolog 2. The notifier is built from config/airbrake.php exactly like the
 * old package did (empty options are dropped so phpbrake applies its defaults).
 */
class AirbrakeLogger
{
    public function __invoke(array $config): Logger
    {
        $options = array_filter(config('airbrake', []));
        $notifier = new Notifier($options);

        $channel = $config['name'] ?? (app()->bound('env') ? app()->environment() : 'production');
        $level = Level::fromName($config['level'] ?? 'debug');

        return new Logger($channel, [
            new AirbrakeHandler($notifier, $level),
        ]);
    }
}
