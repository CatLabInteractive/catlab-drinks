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

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\InstanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * First-run setup: create the founding user and organisation on a fresh
 * instance. Only accessible while no users exist.
 */
class SetupController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showSetupForm()
    {
        if (!InstanceSettings::isSetupRequired()) {
            return redirect('/login');
        }

        return view('setup');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSetup(Request $request)
    {
        if (!InstanceSettings::isSetupRequired()) {
            return redirect('/login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'organisation_name' => ['required', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            // Lock the users table so two concurrent setup submissions
            // cannot both create a founding user. Under InnoDB REPEATABLE
            // READ, this locking read on an empty table takes a next-key
            // lock on the supremum record, blocking concurrent inserts
            // until this transaction commits.
            if (DB::table('users')->lockForUpdate()->count() > 0) {
                return null;
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // The User::created event auto-creates an organisation named
            // after the user; rename it to the submitted organisation name.
            $organisation = $user->organisations()->first();
            if ($organisation) {
                $organisation->name = $data['organisation_name'];
                $organisation->save();
            }

            return $user;
        });

        if (!$user) {
            return redirect('/login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/getting-started');
    }
}
