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

use App\Exceptions\DatabaseErrorClassifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests for the friendly database-error help page shown when the app
 * cannot reach its database or migrations have not been run.
 */
class DatabaseErrorPageTest extends TestCase
{
    private function connectionException(): QueryException
    {
        $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
        $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];

        return new QueryException('select 1', [], $pdo);
    }

    private function missingTableException(): QueryException
    {
        $pdo = new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'db.users' doesn't exist");
        $pdo->errorInfo = ['42S02', 1146, "Table 'db.users' doesn't exist"];

        return new QueryException('select * from `users`', [], $pdo);
    }

    public function testClassifiesConnectionErrors(): void
    {
        $e = $this->connectionException();

        $this->assertTrue(DatabaseErrorClassifier::isConnectionError($e));
        $this->assertTrue(DatabaseErrorClassifier::isDatabaseSetupError($e));
        $this->assertFalse(DatabaseErrorClassifier::isMissingTableError($e));
    }

    public function testClassifiesBarePdoConnectionError(): void
    {
        // Connection failures outside a query are bare PDOExceptions with
        // an int code and no errorInfo.
        $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused', 2002);

        $this->assertTrue(DatabaseErrorClassifier::isConnectionError($pdo));
    }

    public function testClassifiesMissingTables(): void
    {
        $e = $this->missingTableException();

        $this->assertTrue(DatabaseErrorClassifier::isMissingTableError($e));
        $this->assertTrue(DatabaseErrorClassifier::isDatabaseSetupError($e));
        $this->assertFalse(DatabaseErrorClassifier::isConnectionError($e));
    }

    public function testIgnoresOrdinaryExceptions(): void
    {
        $this->assertFalse(DatabaseErrorClassifier::isDatabaseSetupError(new \RuntimeException('nope')));
        $this->assertFalse(DatabaseErrorClassifier::isDatabaseSetupError(
            new QueryException('select 1', [], new \PDOException('Syntax error', 42000))
        ));
    }

    public function testRendersHelpPageForDatabaseErrors(): void
    {
        config(['app.debug' => false]);

        Route::get('/_test/db-error', function () {
            $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
            $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];
            throw new QueryException('select 1', [], $pdo);
        });

        $response = $this->get('/_test/db-error');

        $response->assertStatus(503);
        $response->assertSee('php artisan migrate');
        $response->assertSee('DB_');
    }

    public function testDebugModeKeepsNormalErrorPage(): void
    {
        config(['app.debug' => true]);

        Route::get('/_test/db-error-debug', function () {
            $pdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
            $pdo->errorInfo = ['HY000', 2002, 'Connection refused'];
            throw new QueryException('select 1', [], $pdo);
        });

        $response = $this->get('/_test/db-error-debug');

        $response->assertStatus(500);
    }
}
