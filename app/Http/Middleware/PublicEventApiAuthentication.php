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

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;

/**
 * Class PublicEventApiAuthentication
 * @package App\Http\Middleware
 */
class PublicEventApiAuthentication
{
    const HEADER_KEY = 'X-Event-Token';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->isValidRequest($request)) {
            return $next($request);
        }

        return response()->json([ 'error' => [ 'message' => 'Authentication failed' ]], 403);
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isValidRequest(Request $request)
    {
        // Look for key
        $key = $request->header(self::HEADER_KEY);
        if (!$key) {
            return false;
        }

        $event = Event::getFromOrderToken($key);
        if (!$event) {
            return false;
        }

        $request->merge([ 'event' => $event ]);

        return true;
    }
}