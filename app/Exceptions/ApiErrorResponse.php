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

use CatLab\Charon\Exceptions\EntityNotFoundException;
use CatLab\Charon\Exceptions\InputDecodeException;
use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Charon\Laravel\Exceptions\CharonHttpException;
use CatLab\Charon\Laravel\Exceptions\InputValidatorException;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Exceptions\ValidationException as RequirementsValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Builds the single error format used by both APIs:
 * { "error": { "status": int, "message": string, "issues"?: { property: [ message ] } } }
 */
class ApiErrorResponse
{
    /**
     * @param int $status
     * @param string $message
     * @param array|null $issues
     * @return JsonResponse
     */
    public static function make(int $status, string $message, ?array $issues = null): JsonResponse
    {
        $error = [
            'status' => $status,
            'message' => $message,
        ];

        if ($issues !== null) {
            $error['issues'] = (object) $issues;
        }

        return new JsonResponse(['error' => $error], $status);
    }

    /**
     * @param ResourceValidationException $e
     * @return JsonResponse
     */
    public static function fromValidationException(ResourceValidationException $e): JsonResponse
    {
        return self::make(422, 'Could not decode resource.', $e->getMessages()->toMap());
    }

    /**
     * Convert an exception into an API error response.
     * Returns null for exceptions that should be handled by the default handler
     * (e.g. authentication, which has its own flow, and unexpected errors).
     * @param Throwable $e
     * @return JsonResponse|null
     */
    public static function fromException(Throwable $e): ?JsonResponse
    {
        switch (true) {
            case $e instanceof ResourceValidationException:
                return self::fromValidationException($e);

            case $e instanceof InputValidatorException:
                return self::make(400, 'Input validation failed.', $e->getMessages()->toMap());

            case $e instanceof ValidationException:
                return self::make($e->status, $e->getMessage(), $e->errors());

            case $e instanceof EntityNotFoundException:
            case $e instanceof ModelNotFoundException:
                return self::make(404, 'Resource not found.');

            case $e instanceof NoInputDataFound:
            case $e instanceof InputDecodeException:
                return self::make(400, (string) $e->getErrorMessage());

            case $e instanceof RequirementsValidationException:
            case $e instanceof CharonHttpException:
                return self::make(422, $e->getMessage());

            case $e instanceof HttpExceptionInterface:
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error');
                return self::make($status, $message);
        }

        return null;
    }
}
