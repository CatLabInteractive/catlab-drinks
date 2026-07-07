<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for POST /api/v1/public/order: field stripping and patron assignment.
 * Uses events without an order_token_secret so no signature is needed
 * (signature mechanics are covered by SignedOrderUrlTest).
 */
class PublicOrderPatronAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $organisation = Organisation::factory()->create();
        $this->event = Event::factory()->create([
            'organisation_id' => $organisation->id,
            'order_token' => 'publicordertoken123456789012',
            'order_token_secret' => null,
            'is_selling' => true,
            'allow_unpaid_online_orders' => true,
        ]);

        $this->menuItem = MenuItem::factory()->create([
            'event_id' => $this->event->id,
            'is_selling' => true,
            'price' => 2.5,
        ]);
    }

    private function orderBody(array $extra = []): array
    {
        return array_merge([
            'location' => 'Remote',
            'order' => [
                'items' => [
                    [
                        'menuItem' => ['id' => $this->menuItem->id],
                        'amount' => 1,
                    ],
                ],
            ],
        ], $extra);
    }

    private function postOrder(array $body, array $headers = [])
    {
        return $this->postJson(
            '/api/v1/public/order',
            $body,
            array_merge(['X-Event-Token' => $this->event->order_token], $headers)
        );
    }

    public function testAnonymousTableOrderCreatesPatronAndTable(): void
    {
        $response = $this->postOrder($this->orderBody(), ['X-Table-Number' => '12']);
        $response->assertStatus(200);

        $table = Table::where('event_id', $this->event->id)
            ->where('table_number', 12)->first();
        $this->assertNotNull($table, 'Table 12 should be auto-created');

        $order = Order::where('event_id', $this->event->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals($table->id, $order->table_id);
        $this->assertNotNull($order->patron_id);
        $this->assertEquals(Order::PAYMENT_STATUS_UNPAID, $order->payment_status);
    }

    public function testSecondTableOrderReusesPatronWithOpenTab(): void
    {
        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $firstPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $secondPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        $this->assertEquals($firstPatronId, $secondPatronId);
    }

    public function testSettledTableGetsFreshPatron(): void
    {
        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $firstPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        // Settle the tab.
        Order::where('patron_id', $firstPatronId)->get()->each(function (Order $order) {
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->save();
        });

        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $secondPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        $this->assertNotEquals($firstPatronId, $secondPatronId);
    }

    public function testNamedOrderReusesRecentPatron(): void
    {
        $this->postOrder($this->orderBody(), ['X-Order-Name' => 'Alice'])->assertStatus(200);
        $this->postOrder($this->orderBody(), ['X-Order-Name' => 'Alice'])->assertStatus(200);

        $this->assertEquals(
            1,
            Patron::where('event_id', $this->event->id)->where('name', 'Alice')->count()
        );
    }

    public function testClientCannotInjectTableServiceFields(): void
    {
        $otherOrganisation = Organisation::factory()->create();
        $otherEvent = Event::factory()->create(['organisation_id' => $otherOrganisation->id]);
        $foreignPatron = Patron::factory()->create(['event_id' => $otherEvent->id]);
        $foreignTable = Table::factory()->create(['event_id' => $otherEvent->id]);

        $response = $this->postOrder($this->orderBody([
            'patron_id' => $foreignPatron->id,
            'table_id' => $foreignTable->id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]));
        $response->assertStatus(200);

        $order = Order::where('event_id', $this->event->id)->first();
        $this->assertNull($order->patron_id);
        $this->assertNull($order->table_id);
        // No card, unpaid online orders allowed: must be recorded unpaid,
        // regardless of what the client claimed.
        $this->assertEquals(Order::PAYMENT_STATUS_UNPAID, $order->payment_status);
        $this->assertFalse((bool) $order->paid);
    }
}
