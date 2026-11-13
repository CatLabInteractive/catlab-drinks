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


namespace Tests\Unit;

use App\Support\Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * Tests the opt-out switches and which Airbrake project errors are reported to.
 */
class TelemetryAirbrakeTest extends TestCase
{
    private const VARS = ['APP_ENV', 'AIRBRAKE_PROJECT_ID', 'AIRBRAKE_PROJECT_KEY', 'AIRBRAKE_HOST', 'ERROR_REPORTING'];

    /** @var array<string, ?string> */
    private array $original = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Start every test as a production instance with nothing configured.
        foreach (self::VARS as $var) {
            $value = getenv($var);
            $this->original[$var] = $value === false ? null : $value;
            $this->setEnv($var, null);
        }
        $this->setEnv('APP_ENV', 'production');
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $var => $value) {
            $this->setEnv($var, $value);
        }
        parent::tearDown();
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
            return;
        }
        putenv("$name=$value");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    public function testSwitchIsOnByDefault(): void
    {
        $this->assertTrue(Telemetry::isOn('ERROR_REPORTING'));
    }

    public function testSwitchIsOffByDefaultInLocalAndTesting(): void
    {
        foreach (Telemetry::QUIET_ENVIRONMENTS as $environment) {
            $this->setEnv('APP_ENV', $environment);

            $this->assertFalse(Telemetry::isOn('ERROR_REPORTING'), $environment);
        }
    }

    public function testExplicitTrueTurnsSwitchOnInTesting(): void
    {
        $this->setEnv('APP_ENV', 'testing');
        $this->setEnv('ERROR_REPORTING', 'true');

        $this->assertTrue(Telemetry::isOn('ERROR_REPORTING'));
    }

    public function testOptOutValuesTurnSwitchOff(): void
    {
        foreach (['false', '0', 'off', 'no', ''] as $value) {
            $this->setEnv('ERROR_REPORTING', $value);

            $this->assertFalse(Telemetry::isOn('ERROR_REPORTING'), "ERROR_REPORTING='$value'");
        }
    }

    public function testOwnProjectWinsOverCatlabProject(): void
    {
        $this->setEnv('AIRBRAKE_PROJECT_ID', '123');
        $this->setEnv('AIRBRAKE_PROJECT_KEY', 'own-key');

        $project = Telemetry::airbrakeProject();

        $this->assertSame('123', $project['projectId']);
        $this->assertSame('own-key', $project['projectKey']);
        $this->assertNull($project['keysBlocklist']);
        $this->assertFalse($project['catlab']);
    }

    public function testDefaultUsesCatlabProjectWithBlocklist(): void
    {
        $project = Telemetry::airbrakeProject();

        $this->assertSame(Telemetry::CATLAB_AIRBRAKE_PROJECT_ID, $project['projectId']);
        $this->assertSame((bool) Telemetry::CATLAB_AIRBRAKE_PROJECT_ID, $project['catlab']);
        if (Telemetry::CATLAB_AIRBRAKE_PROJECT_ID) {
            $this->assertSame(Telemetry::AIRBRAKE_KEYS_BLOCKLIST, $project['keysBlocklist']);
        }
    }

    public function testOptOutReportsNowhere(): void
    {
        $this->setEnv('ERROR_REPORTING', 'false');

        $project = Telemetry::airbrakeProject();

        $this->assertNull($project['projectId']);
        $this->assertFalse($project['catlab']);
    }
}
