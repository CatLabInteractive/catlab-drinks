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

namespace App\Exceptions;

/**
 * Classifies exceptions that indicate the database is unreachable or has
 * not been migrated, so a friendly setup-help page can be shown instead
 * of a generic server error.
 */
class DatabaseErrorClassifier
{
    /**
     * MySQL driver error codes for connection-level failures:
     * 1044 access denied to database, 1045 access denied for user,
     * 1049 unknown database, 2002 connection refused / socket,
     * 2006 server has gone away, 2054 auth protocol mismatch.
     */
    private const CONNECTION_ERROR_CODES = [1044, 1045, 1049, 2002, 2006, 2054];

    /**
     * MySQL driver error code for "base table or view not found".
     */
    private const MISSING_TABLE_ERROR_CODE = 1146;

    /**
     * @param \Throwable $e
     * @return bool
     */
    public static function isDatabaseSetupError(\Throwable $e): bool
    {
        return self::isConnectionError($e) || self::isMissingTableError($e);
    }

    /**
     * @param \Throwable $e
     * @return bool
     */
    public static function isConnectionError(\Throwable $e): bool
    {
        $pdoException = self::findPdoException($e);
        if (!$pdoException) {
            return false;
        }

        $code = self::driverErrorCode($pdoException);

        return $code !== null && in_array($code, self::CONNECTION_ERROR_CODES, true);
    }

    /**
     * @param \Throwable $e
     * @return bool
     */
    public static function isMissingTableError(\Throwable $e): bool
    {
        $pdoException = self::findPdoException($e);
        if (!$pdoException) {
            return false;
        }

        return self::driverErrorCode($pdoException) === self::MISSING_TABLE_ERROR_CODE;
    }

    /**
     * @param \PDOException $e
     * @return int|null
     */
    private static function driverErrorCode(\PDOException $e): ?int
    {
        if (isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1])) {
            return (int) $e->errorInfo[1];
        }

        // Connection failures carry the driver code as the exception code.
        if (is_numeric($e->getCode())) {
            return (int) $e->getCode();
        }

        return null;
    }

    /**
     * @param \Throwable $e
     * @return \PDOException|null
     */
    private static function findPdoException(\Throwable $e): ?\PDOException
    {
        while ($e !== null) {
            if ($e instanceof \PDOException) {
                return $e;
            }
            $e = $e->getPrevious();
        }

        return null;
    }
}
