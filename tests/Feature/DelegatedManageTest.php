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

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests the delegated management endpoint called by the accounts server.
 */
class DelegatedManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.catlab.client_secret' => 'shared-secret-123']);
    }

    private function makeSsoUser(int $catlabId): User
    {
        $user = User::query()->create([
            'name' => 'User ' . $catlabId,
            'email' => 'user-' . $catlabId . '-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->catlab_id = $catlabId;
        $user->save();

        return $user->fresh();
    }

    private function insertAccessToken(User $user): string
    {
        $id = 'test-token-' . Str::random(40);
        DB::table('oauth_access_tokens')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'client_id' => 1,
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function callDelegatedEndpoint(array $body)
    {
        return $this->postJson('/delegated/users', $body, [
            'Authorization' => 'Bearer: shared-secret-123',
        ]);
    }

    public function testRejectsWrongSecret(): void
    {
        $response = $this->postJson('/delegated/users', ['user_id' => 1, 'action' => 'logout'], [
            'Authorization' => 'Bearer: wrong-secret',
        ]);

        $response->assertStatus(400);
    }

    public function testRejectsWhenNoSecretConfigured(): void
    {
        config(['services.catlab.client_secret' => null]);

        $response = $this->postJson('/delegated/users', ['user_id' => 1, 'action' => 'logout'], [
            'Authorization' => 'Bearer: ',
        ]);

        $response->assertStatus(400);
    }

    public function testUnknownUserReturns404(): void
    {
        $this->callDelegatedEndpoint(['user_id' => 999999, 'action' => 'delete'])->assertStatus(404);
    }

    public function testDeleteRemovesUserAndMembershipsButKeepsOrganisations(): void
    {
        $user = $this->makeSsoUser(4001);
        $organisation = $user->organisations()->first();
        $tokenId = $this->insertAccessToken($user);

        $this->callDelegatedEndpoint(['user_id' => 4001, 'action' => 'delete'])->assertStatus(200);

        $this->assertNull(User::query()->find($user->id));
        $this->assertSame(0, DB::table('organisation_user')->where('user_id', $user->id)->count());
        $this->assertNotNull($organisation->fresh());
        $this->assertSame(1, (int)DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));
    }

    public function testLogoutRevokesTokensButKeepsUser(): void
    {
        $user = $this->makeSsoUser(4002);
        $tokenId = $this->insertAccessToken($user);

        $this->callDelegatedEndpoint(['user_id' => 4002, 'action' => 'logout'])->assertStatus(200);

        $this->assertNotNull(User::query()->find($user->id));
        $this->assertSame(1, (int)DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));
    }

    public function testUnsupportedUserActionIsANoOp(): void
    {
        $this->makeSsoUser(4003);

        $this->callDelegatedEndpoint(['user_id' => 4003, 'action' => 'activity'])->assertStatus(200);
    }

    public function testProfileActionsAreNotSupported(): void
    {
        $response = $this->callDelegatedEndpoint(['profile_id' => 501, 'action' => 'synchronize-orders']);

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'not supported for profile_id',
            $response->json('error.message')
        );
    }

    public function testMissingIdsRejected(): void
    {
        $this->callDelegatedEndpoint(['action' => 'delete'])->assertStatus(400);
    }
}
