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

use App\Models\Card;
use App\Models\Device;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Opt-in anonymous usage heartbeat (USAGE_REPORTING=true).
 *
 * Sends one Matomo bulk tracking request per interval: a pageview that
 * marks the instance as active, and one event per reported value. Only
 * events are used, so the Matomo site needs no custom dimensions.
 * The Matomo "visitor" is derived from a random instance ID, so the
 * unique visitors report counts instances.
 */
class UsageReporter
{
    public const PROPERTY_INSTANCE_ID = 'instance_id';
    public const PROPERTY_LAST_REPORT = 'usage_last_reported_at';

    /**
     * Cache key that keeps the per-request check off the database.
     */
    private const CHECK_CACHE_KEY = 'usage_reporting.next_check';

    public function isEnabled(): bool
    {
        return config('telemetry.usage.enabled')
            && config('telemetry.usage.matomo_url')
            && config('telemetry.usage.matomo_site_id');
    }

    /**
     * Called after every web/api response: sends a report when one is due.
     * Cheap when nothing is due (one cache read); never throws.
     */
    public function reportIfDue(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            if (Cache::get(self::CHECK_CACHE_KEY, 0) > time()) {
                return;
            }
            Cache::put(self::CHECK_CACHE_KEY, time() + 3600, 3600);

            if ($this->claim()) {
                $this->send($this->collect());
            }
        } catch (\Throwable $e) {
            Log::warning('UsageReporter: report failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Atomically claim the next report slot, so concurrent requests (or
     * several dynos) never send twice in one interval.
     */
    public function claim(bool $force = false): bool
    {
        $now = Carbon::now();
        $due = $now->copy()->subHours((int) config('telemetry.usage.interval_hours', 24));

        DB::table('instance_properties')->insertOrIgnore([
            'key' => self::PROPERTY_LAST_REPORT,
            'value' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $query = DB::table('instance_properties')->where('key', self::PROPERTY_LAST_REPORT);
        if (!$force) {
            $query->where(function ($q) use ($due) {
                $q->whereNull('value')->orWhere('value', '<', $due->toDateTimeString());
            });
        }

        // Microseconds keep the new value distinct from the old one, so MySQL
        // reports the row as changed even for a forced claim in the same second.
        return $query->update(['value' => $now->format('Y-m-d H:i:s.u'), 'updated_at' => $now]) === 1;
    }

    /**
     * Random, persistent ID for this instance. Not derived from the URL or
     * APP_KEY, so it reveals nothing about the instance.
     */
    public function instanceId(): string
    {
        $id = DB::table('instance_properties')->where('key', self::PROPERTY_INSTANCE_ID)->value('value');
        if ($id) {
            return $id;
        }

        DB::table('instance_properties')->insertOrIgnore([
            'key' => self::PROPERTY_INSTANCE_ID,
            'value' => (string) Str::uuid(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Re-read: a concurrent request may have won the insert.
        return DB::table('instance_properties')->where('key', self::PROPERTY_INSTANCE_ID)->value('value');
    }

    /**
     * Everything that is reported. Keep this list in sync with the docs
     * (.ai/usage-reporting.md and the readme).
     *
     * @return array{instance: array<string, string>, usage: array<string, int>}
     */
    public function collect(): array
    {
        $since = Carbon::now()->subDays(30);

        return [
            'instance' => [
                'version' => $this->version(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'database' => DB::connection()->getDriverName(),
                'hosting' => $this->hosting(),
                'sso' => config('services.catlab.client_id') ? 'yes' : 'no',
            ],
            'usage' => [
                'organisations' => Organisation::count(),
                'users' => User::count(),
                'devices' => Device::count(),
                'events' => Event::count(),
                'cards' => Card::count(),
                'orders_30d' => Order::where('created_at', '>=', $since)->count(),
                'active_events_30d' => Order::where('created_at', '>=', $since)->distinct()->count('event_id'),
            ],
        ];
    }

    /**
     * @param array $report as returned by collect()
     */
    public function send(array $report): void
    {
        $base = [
            'idsite' => config('telemetry.usage.matomo_site_id'),
            'rec' => 1,
            'apiv' => 1,
            '_id' => substr(sha1($this->instanceId()), 0, 16),
            'url' => 'https://instance.drinks.catlab.eu/heartbeat',
        ];

        $requests = [
            $base + ['action_name' => 'heartbeat'],
        ];
        foreach ($report['instance'] as $name => $value) {
            $requests[] = $base + ['e_c' => 'instance', 'e_a' => $name, 'e_n' => (string) $value];
        }
        foreach ($report['usage'] as $name => $value) {
            $requests[] = $base + ['e_c' => 'usage', 'e_a' => $name, 'e_v' => $value];
        }

        $response = Http::timeout(5)->post(rtrim(config('telemetry.usage.matomo_url'), '/') . '/matomo.php', [
            'requests' => array_map(fn ($r) => '?' . http_build_query($r), $requests),
        ]);

        if (!$response->successful()) {
            Log::warning('UsageReporter: Matomo returned ' . $response->status());
        }
    }

    private function version(): string
    {
        // Heroku (with dyno metadata) and Dokku expose the deployed commit.
        foreach (['HEROKU_SLUG_COMMIT', 'SOURCE_VERSION', 'GIT_REV'] as $var) {
            if ($commit = getenv($var)) {
                return substr($commit, 0, 12);
            }
        }

        $head = base_path('.git/HEAD');
        if (is_readable($head)) {
            $ref = trim(file_get_contents($head));
            if (str_starts_with($ref, 'ref: ')) {
                $refFile = base_path('.git/' . substr($ref, 5));
                $ref = is_readable($refFile) ? trim(file_get_contents($refFile)) : '';
            }
            if (preg_match('/^[0-9a-f]{12,}$/', $ref)) {
                return substr($ref, 0, 12);
            }
        }

        return 'unknown';
    }

    private function hosting(): string
    {
        if (getenv('DYNO')) {
            return 'heroku';
        }
        if (file_exists('/.dockerenv') || getenv('DOKKU_APP_TYPE')) {
            return 'docker';
        }
        return 'other';
    }
}
