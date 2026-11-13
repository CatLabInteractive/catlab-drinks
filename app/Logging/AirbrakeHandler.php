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

use Airbrake\Errors\Base as AirbrakeError;
use Airbrake\Notifier;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Monolog 3 port of Airbrake\MonologHandler (phpbrake still ships the
 * Monolog 2 signature, which no longer loads against Laravel 10+).
 */
class AirbrakeHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly Notifier $notifier,
        int|string|Level $level = Level::Error,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $exception = $record->context['exception'] ?? null;
        if (!$exception instanceof Throwable) {
            $exception = new AirbrakeError($record->message, array_slice(debug_backtrace(), 3));
        }

        $notice = $this->notifier->buildNotice($exception);
        $notice['errors'][0]['type'] = $record->channel . '.' . $record->level->getName();
        $notice['context']['severity'] = $record->level->getName();
        if (!empty($record->context)) {
            $notice['params']['monolog_context'] = $record->context;
        }
        if (!empty($record->extra)) {
            $notice['params']['monolog_extra'] = $record->extra;
        }

        $this->notifier->sendNotice($notice);
    }
}
