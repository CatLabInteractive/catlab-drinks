<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Every API error response must use the same shape:
 * { error: { status, message, issues? } }
 * The frontends rely on it (see AbstractService's error interceptor).
 */
class ApiErrorFormatTest extends TestCase
{
	use RefreshDatabase;

	private Organisation $organisation;
	private Device $device;
	private User $user;

	protected function setUp(): void
	{
		parent::setUp();

		$this->organisation = Organisation::factory()->create();

		$this->device = Device::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);

		$this->user = User::query()->create([
			'name' => 'Test User',
			'email' => 'test-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);

		$this->organisation->users()->attach($this->user->id);
	}

	public function testUncaughtResourceValidationExceptionUsesErrorFormat(): void
	{
		Passport::actingAs($this->user);
		$response = $this->postJson('/api/v1/devices/' . $this->device->id . '/license', [
			'license_key' => 'not base64!',
		]);

		$response->assertStatus(422);
		$response->assertJsonPath('error.status', 422);
		$this->assertContains(
			'Invalid license key: not valid base64.',
			Arr::flatten($response->json('error.issues'))
		);
		$response->assertJsonMissingPath('errors');
	}

	public function testAbortUsesErrorFormat(): void
	{
		$this->organisation->profile_id = 501;
		$this->organisation->save();

		Passport::actingAs($this->user);
		$response = $this->putJson('/api/v1/organisations/' . $this->organisation->id, [
			'name' => 'Renamed',
		]);

		$response->assertStatus(422);
		$response->assertJsonPath('error.status', 422);
		$response->assertJsonPath('error.message', 'This organisation\'s name is managed on CatLab Accounts.');
	}

	public function testModelNotFoundUsesErrorFormat(): void
	{
		Passport::actingAs($this->user);
		$response = $this->postJson('/api/v1/devices/999999999/license', [
			'license_key' => 'irrelevant',
		]);

		$response->assertStatus(404);
		$response->assertJsonPath('error.status', 404);
		$this->assertIsString($response->json('error.message'));
		$response->assertJsonMissingPath('errors');
	}
}
