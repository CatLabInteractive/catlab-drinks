<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Round-trip coverage for the table service event settings:
 * writable through the Management API, read-only but visible on the
 * Device API (the POS reads them to decide queue routing).
 */
class EventTableServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->event = Event::factory()->create([
            'organisation_id' => $this->organisation->id,
            'allow_unpaid_table_orders' => false,
            'bar_prepares_table_orders' => false,
        ]);
    }

    public function testManagementApiWritesSettings(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->organisation->users()->attach($user->id);
        Passport::actingAs($user);

        $response = $this->putJson('/api/v1/events/' . $this->event->id, [
            'name' => $this->event->name,
            'allow_unpaid_table_orders' => true,
            'bar_prepares_table_orders' => true,
        ]);

        $response->assertStatus(200);

        $fresh = $this->event->fresh();
        $this->assertTrue((bool) $fresh->allow_unpaid_table_orders);
        $this->assertTrue((bool) $fresh->bar_prepares_table_orders);

        $view = $this->getJson('/api/v1/events/' . $this->event->id);
        $view->assertStatus(200);
        $view->assertJsonFragment([
            'allow_unpaid_table_orders' => true,
            'bar_prepares_table_orders' => true,
        ]);
    }

    public function testDeviceApiExposesButDoesNotWriteSettings(): void
    {
        $this->event->allow_unpaid_table_orders = true;
        $this->event->bar_prepares_table_orders = true;
        $this->event->save();

        $device = Device::factory()->create([
            'organisation_id' => $this->organisation->id,
        ]);
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $token = new DeviceAccessToken([
            'device_id' => $device->id,
            'access_token' => 'device-settings-token',
            'expires_at' => now()->addHour(),
        ]);
        $token->created_by = $user->id;
        $token->save();

        $view = $this
            ->withHeader('Authorization', 'Bearer ' . $token->access_token)
            ->getJson('/pos-api/v1/events/' . $this->event->id);

        $view->assertStatus(200);
        $view->assertJsonFragment([
            'allow_unpaid_table_orders' => true,
            'bar_prepares_table_orders' => true,
        ]);
    }
}
