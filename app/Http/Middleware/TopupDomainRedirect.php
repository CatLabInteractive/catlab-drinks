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

use Closure;
use Illuminate\Http\Request;

/**
 * Class TopupDomainRedirect
 *
 * Redirects requests coming from the topup domain to the topup page.
 * The topup domain is a short domain written to NFC cards that redirects
 * to the card-specific topup page.
 *
 * @package App\Http\Middleware
 */
class TopupDomainRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $topupDomains = config('app.topup_domain_name', []);

        if (!is_array($topupDomains)) {
            $topupDomains = [$topupDomains];
        }

        $host = $request->getHost();

        // Check if the request is coming from one of the topup domains
        if (in_array($host, $topupDomains)) {
            $path = $request->path();

            // If the path is already a topup path, continue
            if (str_starts_with($path, 'topup/') || $path === 'topup') {
                return $next($request);
            }

            // Redirect to the topup page with the path as the card ID
            // The path should be the card UID
            if ($path && $path !== '/') {
                return redirect('/topup/' . $path);
            }
        }

        return $next($request);
    }
}

