<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Table;
use App\Models\User;
use App\Services\PatronAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The unique index on tables(event_id, table_number) covers soft-deleted rows
 * too. Every path that assigns a table number must therefore either reuse the
 * trashed row (restore) or reject the number — never run into the database
 * constraint.
 */
class TableNumberCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $attributes = []): Event
    {
        $organisation = Organisation::factory()->create();

        return Event::factory()->create(array_merge([
            'organisation_id' => $organisation->id,
        ], $attributes));
    }

    public function testFindOrCreateTableRestoresSoftDeletedTable(): void
    {
        $event = $this->makeEvent();
        $table = Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 5,
            'name' => 'VIP 5',
        ]);
        $table->delete();

        $service = new PatronAssignmentService();
        $result = $service->findOrCreateTable($event, 5);

        $this->assertEquals($table->id, $result->id);
        $this->assertFalse($result->trashed());
        $this->assertEquals('VIP 5', $result->name);
        $this->assertEquals(1, Table::withTrashed()
            ->where('event_id', $event->id)
            ->where('table_number', 5)
            ->count());
    }

    public function testBulkGenerateRestoresSoftDeletedNumbers(): void
    {
        $event = $this->makeEvent();
        $tables = Table::bulkGenerate($event, 3);
        $this->assertCount(3, $tables);

        // Soft-delete table 3; the next highest active number is 2, so a new
        // generate run wants to create number 3 again.
        $tables[2]->delete();

        $regenerated = Table::bulkGenerate($event, 2);

        $this->assertCount(2, $regenerated);
        $this->assertEquals([3, 4], array_map(
            fn (Table $t) => (int) $t->table_number,
            $regenerated
        ));
        $this->assertEquals($tables[2]->id, $regenerated[0]->id);
        $this->assertFalse($regenerated[0]->trashed());
    }

    public function testPublicOrderForSoftDeletedTableNumberRestoresTable(): void
    {
        $event = $this->makeEvent([
            'order_token' => 'collisiontesttoken1234567890',
            'order_token_secret' => null,
            'is_selling' => true,
            'allow_unpaid_online_orders' => true,
        ]);
        $menuItem = MenuItem::factory()->create([
            'event_id' => $event->id,
            'is_selling' => true,
            'price' => 2.5,
        ]);

        $table = Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 12,
        ]);
        $table->delete();

        $response = $this->postJson('/api/v1/public/order', [
            'location' => 'Remote',
            'order' => [
                'items' => [
                    [
                        'menuItem' => ['id' => $menuItem->id],
                        'amount' => 1,
                    ],
                ],
            ],
        ], [
            'X-Event-Token' => $event->order_token,
            'X-Table-Number' => '12',
        ]);

        $response->assertStatus(200);

        $order = Order::where('event_id', $event->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals($table->id, $order->table_id);
        $this->assertFalse($table->fresh()->trashed());
    }

    public function testCreatingTableWithDuplicateNumberReturns422(): void
    {
        $event = $this->makeEvent();
        Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 4,
        ]);

        $user = $this->makeAuthorizedUser($event);
        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/events/' . $event->id . '/tables', [
            'table_number' => 4,
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function testCreatingTableWithTrashedDuplicateNumberReturns422(): void
    {
        $event = $this->makeEvent();
        $table = Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 4,
        ]);
        $table->delete();

        $user = $this->makeAuthorizedUser($event);
        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/events/' . $event->id . '/tables', [
            'table_number' => 4,
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function testEditingTableIntoCollisionReturns422(): void
    {
        $event = $this->makeEvent();
        Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 1,
        ]);
        $second = Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 2,
        ]);

        $user = $this->makeAuthorizedUser($event);
        Passport::actingAs($user);

        $response = $this->putJson('/api/v1/tables/' . $second->id, [
            'table_number' => 1,
            'name' => $second->name,
        ]);

        $response->assertStatus(422);
    }

    public function testRenamingTableWithoutChangingNumberIsAllowed(): void
    {
        $event = $this->makeEvent();
        $table = Table::factory()->create([
            'event_id' => $event->id,
            'table_number' => 1,
        ]);

        $user = $this->makeAuthorizedUser($event);
        Passport::actingAs($user);

        $response = $this->putJson('/api/v1/tables/' . $table->id, [
            'table_number' => 1,
            'name' => 'Renamed',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Renamed', $table->fresh()->name);
    }

    private function makeAuthorizedUser(Event $event): User
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        $event->organisation->users()->attach($user->id);

        return $user;
    }
}
