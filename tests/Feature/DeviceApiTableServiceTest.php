<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP smoke tests for the table service routes on the Device (POS) API:
 * a paired device can read and write tables/patrons and settle a tab for
 * its own organisation's events, cannot touch other organisations, and
 * has no destroy routes.
 */
class DeviceApiTableServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;
    private Event $event;
    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->event = Event::factory()->create([
            'organisation_id' => $this->organisation->id,
            'allow_unpaid_table_orders' => true,
        ]);

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
            'access_token' => 'device-table-service-token',
            'expires_at' => now()->addHour(),
        ]);
        $token->created_by = $user->id;
        $token->save();

        $this->accessToken = $token->access_token;
    }

    private function asDevice()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->accessToken);
    }

    public function testDeviceCanListTables(): void
    {
        $table = Table::factory()->create([
            'event_id' => $this->event->id,
            'table_number' => 1,
        ]);

        $response = $this->asDevice()
            ->getJson('/pos-api/v1/events/' . $this->event->id . '/tables');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $table->id]);
    }

    public function testDeviceCannotListTablesOfOtherOrganisation(): void
    {
        $otherEvent = Event::factory()->create([
            'organisation_id' => Organisation::factory()->create()->id,
        ]);

        $this->asDevice()
            ->getJson('/pos-api/v1/events/' . $otherEvent->id . '/tables')
            ->assertStatus(403);
    }

    public function testDeviceCanRenameTable(): void
    {
        $table = Table::factory()->create([
            'event_id' => $this->event->id,
            'table_number' => 1,
        ]);

        $response = $this->asDevice()
            ->putJson('/pos-api/v1/tables/' . $table->id, [
                'table_number' => 1,
                'name' => 'Terrace 1',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Terrace 1', $table->fresh()->name);
    }

    public function testDeviceHasNoTableDestroyRoute(): void
    {
        $table = Table::factory()->create([
            'event_id' => $this->event->id,
            'table_number' => 1,
        ]);

        $this->asDevice()
            ->deleteJson('/pos-api/v1/tables/' . $table->id)
            ->assertStatus(405);

        $this->assertFalse($table->fresh()->trashed());
    }

    public function testDeviceCanCreatePatronAtTable(): void
    {
        $table = Table::factory()->create([
            'event_id' => $this->event->id,
            'table_number' => 1,
        ]);

        $response = $this->asDevice()
            ->postJson('/pos-api/v1/events/' . $this->event->id . '/patrons', [
                'name' => 'Walk-in',
                'table_id' => $table->id,
            ]);

        $response->assertStatus(201);

        $patron = Patron::where('event_id', $this->event->id)->first();
        $this->assertNotNull($patron);
        $this->assertEquals($table->id, $patron->table_id);
    }

    public function testDeviceCanSettlePatron(): void
    {
        $patron = Patron::factory()->create(['event_id' => $this->event->id]);

        $order = Order::factory()->make(['event_id' => $this->event->id]);
        $order->patron_id = $patron->id;
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $response = $this->asDevice()
            ->postJson('/pos-api/v1/patrons/' . $patron->id . '/settle', [
                'payment_type' => 'cash',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(
            Order::PAYMENT_STATUS_PAID,
            $order->fresh()->payment_status
        );
    }

    public function testDeviceCannotSettlePatronOfOtherOrganisation(): void
    {
        $otherEvent = Event::factory()->create([
            'organisation_id' => Organisation::factory()->create()->id,
        ]);
        $patron = Patron::factory()->create(['event_id' => $otherEvent->id]);

        $this->asDevice()
            ->postJson('/pos-api/v1/patrons/' . $patron->id . '/settle', [
                'payment_type' => 'cash',
            ])
            ->assertStatus(403);
    }
}
