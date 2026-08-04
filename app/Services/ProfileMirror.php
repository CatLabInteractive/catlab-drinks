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

namespace App\Services;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors CatLab accounts "profiles" into local organisations.
 *
 * Port of the QuizWitz ProfileMirror (list + roster only; drinks has no
 * licenses). Pull-based: runs on SSO login and throttled on authenticated
 * requests, using the user's stored accounts access token.
 *
 * @see docs/superpowers/specs/2026-08-04-profiles-organisations-sync-design.md
 */
class ProfileMirror
{
    /**
     * Minimum seconds between unforced syncs per user.
     */
    const THROTTLE_SECONDS = 900;

    /**
     * After a failed sync, retry opens after this many seconds instead of
     * hammering accounts on every request during an outage.
     */
    const FAILURE_RETRY_SECONDS = 60;

    /**
     * Sync all of the user's accounts profiles into local organisations.
     * Never throws for transport-level problems; callers should still wrap
     * calls in try/catch so an unexpected error never breaks auth.
     *
     * @param User $user
     * @param bool $force Skip the throttle and the per-profile version skip guard.
     */
    public function sync(User $user, bool $force = false): void
    {
        if (config('services.catlab.disable_profile_mirror')) {
            return;
        }

        if (empty($user->catlab_access_token)) {
            return;
        }

        if ($force) {
            $this->stampSync($user);
        } elseif (!$this->claimSync($user)) {
            return;
        }

        $items = $this->fetchItems($this->getProfilesUrl(), $user->catlab_access_token);

        if ($items === null) {
            Log::warning('ProfileMirror: could not fetch profiles list', ['user' => $user->id]);
            $this->stampFailureBackoff($user);
            return;
        }

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }

            if (!$this->syncProfile($user, $item, $force)) {
                $this->stampFailureBackoff($user);
                return;
            }
        }

        $this->stampSync($user);

        // Authorization reads the cached relation; make sure checks later in
        // this request see the fresh membership.
        $user->unsetRelation('organisations');
    }

    /**
     * Sync a single profile list item into an organisation.
     * @param User $user
     * @param array $item ['id' =>, 'name' =>, 'role' =>, 'personal' =>, 'version' =>?]
     * @param bool $force
     * @return bool false aborts the remaining sync and triggers failure backoff.
     */
    protected function syncProfile(User $user, array $item, bool $force): bool
    {
        $profileId = (int)$item['id'];
        $name = trim((string)($item['name'] ?? ''));

        $organisation = Organisation::query()->where('profile_id', $profileId)->first();
        $created = false;

        if (!$organisation && !empty($item['personal'])) {
            $organisation = $this->adoptPersonalOrganisation($user, $profileId);
        }

        if (!$organisation) {
            [$organisation, $created] = $this->createOrganisation($user, $profileId, $name);
        }

        if (!$organisation) {
            // Lost a link race and could not resolve the winner; retry later.
            return false;
        }

        // The accounts name is canonical.
        if ($name !== '' && $organisation->name !== $name) {
            $organisation->name = $name;
            $organisation->save();
        }

        // Incremental-sync skip guard: nothing changed on accounts since the
        // stored version AND this user is already a local member (a member
        // added accounts-side before their own first login must not be
        // skipped: their membership only lands when they sync themselves).
        $version = array_key_exists('version', $item) ? (int)$item['version'] : null;

        if (!$force && !$created && $version !== null
            && $organisation->profile_sync_version !== null
            && (int)$organisation->profile_sync_version === $version
            && $organisation->users()->whereKey($user->id)->exists()) {
            return true;
        }

        // Roster. A failed fetch must never wipe membership.
        $members = $this->fetchItems($this->getMembersUrl($profileId), $user->catlab_access_token);
        if ($members === null) {
            Log::warning('ProfileMirror: could not fetch members', [
                'user' => $user->id,
                'profile' => $profileId,
            ]);
            return false;
        }

        $catlabIds = [];
        foreach ($members as $member) {
            if (is_array($member) && isset($member['userId'])) {
                $catlabIds[] = (int)$member['userId'];
            }
        }

        // Unknown members are skipped; they are picked up at their own first
        // login (guaranteed by the skip guard's membership term above).
        $localUserIds = User::query()
            ->whereIn('catlab_id', $catlabIds)
            ->pluck('id')
            ->all();

        // Apply + stamp serialized per organisation: last-to-apply is also
        // last-to-stamp, so a stale applier stamps its own stale version and
        // the next sync self-heals.
        DB::transaction(function () use ($organisation, $localUserIds, $version) {
            $locked = Organisation::query()
                ->whereKey($organisation->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return;
            }

            $locked->users()->sync($localUserIds);
            $locked->profile_sync_version = $version;
            $locked->save();
        });

        $organisation->unsetRelation('users');

        return true;
    }

    /**
     * Adopt the oldest unlinked organisation this user belongs to (the
     * User::created hook guarantees one exists for pre-profiles users).
     * @return Organisation|null
     */
    protected function adoptPersonalOrganisation(User $user, int $profileId)
    {
        $candidate = $user->organisations()
            ->whereNull('profile_id')
            ->orderBy('organisations.id')
            ->first();

        if (!$candidate) {
            return null;
        }

        try {
            $this->linkProfile($candidate, $profileId);
            return $candidate;
        } catch (QueryException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            // A concurrent request linked this profile first; use the winner.
            return Organisation::query()->where('profile_id', $profileId)->first();
        }
    }

    /**
     * Create a new organisation for a profile and link it.
     * @return array [Organisation|null, bool $created]
     */
    protected function createOrganisation(User $user, int $profileId, string $name)
    {
        $organisation = new Organisation([
            'name' => $name !== '' ? $name : 'Organisation ' . $profileId,
        ]);
        $organisation->save();
        $organisation->users()->attach($user->id);

        try {
            $this->linkProfile($organisation, $profileId);
            return [$organisation, true];
        } catch (QueryException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            // Lost the creation race: remove the husk (it has no content yet)
            // and adopt the winner.
            $organisation->users()->detach();
            $organisation->delete();

            return [Organisation::query()->where('profile_id', $profileId)->first(), false];
        }
    }

    /**
     * Link an organisation to an accounts profile. Separate seam so the
     * race-condition tests can interpose; throws QueryException (1062) when
     * another organisation already holds the profile_id.
     */
    protected function linkProfile(Organisation $organisation, int $profileId): void
    {
        $organisation->profile_id = $profileId;
        $organisation->save();
    }

    /**
     * @param QueryException $e
     * @return bool true when the exception is a MySQL duplicate-key (1062).
     */
    protected function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /**
     * GET an accounts collection endpoint. Returns the items array on a
     * well-formed 200, null on any transport error / non-200 / bad body
     * (the uniform abort signal).
     * @return array|null
     */
    protected function fetchItems(string $url, string $bearer)
    {
        try {
            $response = Http::withToken($bearer)
                ->timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url);
        } catch (\Exception $e) {
            return null;
        }

        if ($response->status() !== 200) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return null;
        }

        return $data['items'];
    }

    /**
     * Claim the right to sync with one atomic conditional update; only the
     * winner proceeds. The in-memory pre-check makes steady-state requests
     * free (no extra query).
     */
    protected function claimSync(User $user): bool
    {
        $last = $user->last_profile_sync;
        if ($last && $last->gt(now()->subSeconds(self::THROTTLE_SECONDS))) {
            return false;
        }

        $claimed = User::query()
            ->whereKey($user->id)
            ->where(function ($query) {
                $query->whereNull('last_profile_sync')
                    ->orWhere('last_profile_sync', '<', now()->subSeconds(self::THROTTLE_SECONDS));
            })
            ->update(['last_profile_sync' => now()]);

        if ($claimed > 0) {
            $user->last_profile_sync = now();
            return true;
        }

        return false;
    }

    protected function stampSync(User $user): void
    {
        $user->last_profile_sync = now();
        User::query()->whereKey($user->id)->update(['last_profile_sync' => now()]);
    }

    /**
     * Stamp so that the next unforced sync is allowed FAILURE_RETRY_SECONDS
     * from now instead of THROTTLE_SECONDS.
     */
    protected function stampFailureBackoff(User $user): void
    {
        $stamp = now()->subSeconds(self::THROTTLE_SECONDS - self::FAILURE_RETRY_SECONDS);
        $user->last_profile_sync = $stamp;
        User::query()->whereKey($user->id)->update(['last_profile_sync' => $stamp]);
    }

    protected function getBaseUrl(): string
    {
        return rtrim((string)config('services.catlab.url'), '/');
    }

    protected function getProfilesUrl(): string
    {
        return $this->getBaseUrl() . '/api/1.0/profiles';
    }

    protected function getMembersUrl(int $profileId): string
    {
        return $this->getBaseUrl() . '/api/1.0/profiles/' . $profileId . '/members';
    }
}
