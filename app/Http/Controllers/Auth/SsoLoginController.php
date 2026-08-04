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

namespace App\Http\Controllers\Auth;

use App\Support\InstanceSettings;
use CatLab\Accounts\Client\Controllers\LoginController as CatLabLoginController;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * SSO login controller that refuses to create new local users while
 * registration is closed. Existing users log in normally.
 */
class SsoLoginController extends CatLabLoginController
{
    /**
     * @param mixed $socialiteUser
     * @return mixed
     */
    protected function getUserFromSocialite($socialiteUser)
    {
        $existing = $this->getUserFromCatLabId($socialiteUser->getId());

        if (!$existing && !InstanceSettings::isRegistrationOpen()) {
            throw new HttpResponseException(
                response()->view('auth.registration-closed', [], 403)
            );
        }

        return parent::getUserFromSocialite($socialiteUser);
    }
}
