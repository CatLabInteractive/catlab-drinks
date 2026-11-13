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


namespace Tests\Feature;

use App\Models\User;
use App\Services\UsageReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests for the opt-out anonymous usage report (USAGE_REPORTING) and the
 * footer notice that tells the owner about it.
 */
class UsageReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test environment is a quiet one: reporting is off unless asked for.
        $this->assertFalse(config('telemetry.usage.enabled'));

        config([
            'telemetry.usage.matomo_url' => 'https://matomo.test/',
            'telemetry.usage.matomo_site_id' => 7,
        ]);

        Route::get('/_test/web-ping', function () {
            return response('pong');
        })->middleware('web');

        Http::fake(['matomo.test/*' => Http::response('', 204)]);
    }

    private function enable(): void
    {
        config(['telemetry.usage.enabled' => true]);
    }

    public function testFooterTellsWhatIsReported(): void
    {
        $this->enable();
        $this->get('/setup')->assertOk()
            ->assertSee('This instance sends anonymous usage statistics to CatLab.')
            ->assertSee('#usage-and-error-reporting');

        config(['telemetry.errors.to_catlab' => true]);
        $this->get('/setup')
            ->assertSee('This instance sends anonymous usage statistics and error reports to CatLab.');

        config(['telemetry.usage.enabled' => false]);
        $this->get('/setup')->assertSee('This instance sends error reports to CatLab.');
    }

    public function testFooterIsSilentWhenNothingIsReported(): void
    {
        $this->get('/setup')->assertOk()->assertDontSee('telemetry-notice');
    }

    public function testNothingIsSentWhenDisabled(): void
    {
        $this->get('/_test/web-ping')->assertOk();

        Http::assertNothingSent();
    }

    public function testNothingIsSentWithoutMatomoSite(): void
    {
        $this->enable();
        config(['telemetry.usage.matomo_site_id' => null]);

        $this->get('/_test/web-ping')->assertOk();

        Http::assertNothingSent();
    }

    public function testWebRequestSendsOneBulkReport(): void
    {
        $this->enable();

        $this->get('/_test/web-ping')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://matomo.test/matomo.php') {
                return false;
            }

            $requests = $request->data()['requests'];
            parse_str(ltrim($requests[0], '?'), $heartbeat);

            return $heartbeat['action_name'] === 'heartbeat'
                && $heartbeat['idsite'] == 7
                && preg_match('/^[0-9a-f]{16}$/', $heartbeat['_id'])
                && collect($requests)->contains(fn ($r) => str_contains($r, 'e_a=orders_30d'));
        });
    }

    public function testReportIsSentAtMostOncePerInterval(): void
    {
        $this->enable();

        $this->get('/_test/web-ping');
        Cache::flush(); // bypass the hourly check marker, hit the DB claim
        $this->get('/_test/web-ping');

        Http::assertSentCount(1);
    }

    public function testClaimIsExclusiveUnlessForced(): void
    {
        $reporter = app(UsageReporter::class);

        $this->assertTrue($reporter->claim());
        $this->assertFalse($reporter->claim());
        $this->assertTrue($reporter->claim(true));
    }

    public function testInstanceIdIsStable(): void
    {
        $reporter = app(UsageReporter::class);

        $this->assertSame($reporter->instanceId(), $reporter->instanceId());
    }

    public function testReportContainsNoPersonalData(): void
    {
        User::query()->create([
            'name' => 'Private Person',
            'email' => 'private@example.com',
            'password' => bcrypt('secret'),
        ]);

        $report = json_encode(app(UsageReporter::class)->collect());

        $this->assertStringNotContainsString('private@example.com', $report);
        $this->assertStringNotContainsString('Private Person', $report);
        $this->assertStringContainsString('"users":1', $report);
    }

    public function testDryRunSendsNothing(): void
    {
        $this->enable();

        $this->artisan('drinks:report-usage', ['--dry-run' => true])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function testCommandSendsWhenEnabled(): void
    {
        $this->enable();

        $this->artisan('drinks:report-usage')->assertSuccessful();

        Http::assertSentCount(1);
    }
}
