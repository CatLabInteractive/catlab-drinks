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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Delegated management endpoint for the CatLab accounts server
 * (the manage_user_uri configured on the drinks OAuth client).
 *
 * Contract: unknown user => 404 (accounts reads that as "already removed");
 * unsupported user actions => 200 no-op so accounts flows never break;
 * profile actions are not supported here.
 */
class DelegatedManageController
{
    public function manage(Request $request)
    {
        if ($request->input('user_id')) {
            return $this->manageUser($request);
        }

        if ($request->input('profile_id')) {
            return response()->json([
                'error' => [
                    'message' => sprintf(
                        'Action %s is not supported for profile_id',
                        $request->input('action')
                    ),
                ],
            ], 400);
        }

        return response()->json([
            'error' => ['message' => 'user_id or profile_id is required.'],
        ], 400);
    }

    private function manageUser(Request $request)
    {
        $user = User::query()
            ->where('catlab_id', $request->input('user_id'))
            ->first();

        if (!$user) {
            return response()->json([
                'error' => ['message' => 'User not found.'],
            ], 404);
        }

        switch ($request->input('action')) {
            case 'delete':
                $this->revokeTokens($user);
                $user->organisations()->detach();
                $user->delete();
                return response()->json(['success' => true]);

            case 'logout':
                $this->revokeTokens($user);
                return response()->json(['success' => true]);

            default:
                // Drinks tracks nothing for other actions (info, activity, ...).
                return response()->json([]);
        }
    }

    private function revokeTokens(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');
        if ($tokenIds->isEmpty()) {
            return;
        }

        DB::table('oauth_refresh_tokens')
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);

        $user->tokens()->update(['revoked' => true]);
    }
}
