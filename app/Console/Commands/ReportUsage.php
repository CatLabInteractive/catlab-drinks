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


namespace App\Console\Commands;

use App\Services\UsageReporter;
use Illuminate\Console\Command;

/**
 * Sends (or shows) the opt-in anonymous usage report.
 */
class ReportUsage extends Command
{
    protected $signature = 'drinks:report-usage
        {--dry-run : Print what would be reported without sending anything}
        {--force : Send even if a report went out within the last interval}';

    protected $description = 'Send the opt-in anonymous usage report (USAGE_REPORTING=true)';

    public function handle(UsageReporter $reporter): int
    {
        if ($this->option('dry-run')) {
            $this->line(json_encode($reporter->collect(), JSON_PRETTY_PRINT));
            $this->info($reporter->isEnabled()
                ? 'Usage reporting is enabled; this is what is sent.'
                : 'Usage reporting is disabled; nothing is sent.');
            return self::SUCCESS;
        }

        if (!$reporter->isEnabled()) {
            $this->warn(config('telemetry.usage.enabled')
                ? 'Usage reporting is on, but no Matomo URL / site ID is configured.'
                : 'Usage reporting is disabled (set USAGE_REPORTING=true to opt in).');
            return self::SUCCESS;
        }

        if (!$reporter->claim((bool) $this->option('force'))) {
            $this->info('A report was already sent recently; use --force to send anyway.');
            return self::SUCCESS;
        }

        $reporter->send($reporter->collect());
        $this->info('Usage report sent.');
        return self::SUCCESS;
    }
}
