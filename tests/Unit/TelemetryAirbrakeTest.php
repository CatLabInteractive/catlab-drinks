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
 * Tests which Airbrake project errors are reported to.
 */
class TelemetryAirbrakeTest extends TestCase
{
    private const VARS = ['AIRBRAKE_PROJECT_ID', 'AIRBRAKE_PROJECT_KEY', 'AIRBRAKE_HOST', 'ERROR_REPORTING'];

    protected function tearDown(): void
    {
        foreach (self::VARS as $var) {
            $this->setEnv($var, null);
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

    public function testDisabledByDefault(): void
    {
        $this->assertNull(Telemetry::airbrakeProject()['projectId']);
    }

    public function testOwnProjectWinsOverOptIn(): void
    {
        $this->setEnv('AIRBRAKE_PROJECT_ID', '123');
        $this->setEnv('AIRBRAKE_PROJECT_KEY', 'own-key');
        $this->setEnv('ERROR_REPORTING', 'true');

        $project = Telemetry::airbrakeProject();

        $this->assertSame('123', $project['projectId']);
        $this->assertSame('own-key', $project['projectKey']);
        $this->assertNull($project['keysBlocklist']);
    }

    public function testOptInUsesCatlabProjectWithBlocklist(): void
    {
        $this->setEnv('ERROR_REPORTING', 'true');

        $project = Telemetry::airbrakeProject();

        $this->assertSame(Telemetry::CATLAB_AIRBRAKE_PROJECT_ID, $project['projectId']);
        if (Telemetry::CATLAB_AIRBRAKE_PROJECT_ID) {
            $this->assertSame(Telemetry::AIRBRAKE_KEYS_BLOCKLIST, $project['keysBlocklist']);
        }
    }
}
