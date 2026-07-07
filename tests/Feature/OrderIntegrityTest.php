<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tests for the model-level integrity rules on Order and Patron.
 * These rules must hold on every write path (management API, device API,
 * public order endpoint), which is why they live in model events.
 */
class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $attributes = []): Event
    {
        $organisation = Organisation::factory()->create();

        return Event::factory()->create(array_merge([
            'organisation_id' => $organisation->id,
            'allow_unpaid_table_orders' => false,
            'allow_unpaid_online_orders' => false,
        ], $attributes));
    }

    public function testOrderRejectsPatronFromOtherEvent(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $patronB = Patron::factory()->create(['event_id' => $eventB->id]);

        $order = Order::factory()->make(['event_id' => $eventA->id]);
        $order->patron_id = $patronB->id;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testOrderAcceptsPatronFromSameEvent(): void
    {
        $event = $this->makeEvent();
        $patron = Patron::factory()->create(['event_id' => $event->id]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->patron_id = $patron->id;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'patron_id' => $patron->id,
        ]);
    }

    public function testOrderRejectsUnknownPatron(): void
    {
        $event = $this->makeEvent();

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->patron_id = 999999;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testOrderRejectsTableFromOtherEvent(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $tableB = Table::factory()->create(['event_id' => $eventB->id]);

        $order = Order::factory()->make(['event_id' => $eventA->id]);
        $order->table_id = $tableB->id;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testOrderRejectsInvalidPaymentStatus(): void
    {
        $event = $this->makeEvent();

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = 'totally-paid-i-promise';

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testUnpaidRequiresEventSetting(): void
    {
        $event = $this->makeEvent(); // both unpaid flags false

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testUnpaidAllowedWithTableOrdersSetting(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'paid' => false,
        ]);
    }

    public function testUnpaidAllowedWithOnlineOrdersSetting(): void
    {
        $event = $this->makeEvent(['allow_unpaid_online_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);
    }

    public function testUnpaidToPaidAlwaysAllowedAndSyncsPaidBool(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        // Turn the setting off: settling must still work.
        $event->allow_unpaid_table_orders = false;
        $event->save();

        $order->refresh();
        $order->payment_status = Order::PAYMENT_STATUS_PAID;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid' => true,
        ]);
    }

    public function testVoidAlwaysAllowed(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $order->refresh();
        $order->payment_status = Order::PAYMENT_STATUS_VOIDED;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_VOIDED,
            'paid' => false,
        ]);
    }

    public function testLegacyPaidWriteSyncsPaymentStatus(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        // Legacy flow: only sets `paid` (e.g. bar accepting a remote order).
        $order->refresh();
        $order->paid = true;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid' => true,
        ]);
    }

    public function testPatronRejectsTableFromOtherEvent(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $tableB = Table::factory()->create(['event_id' => $eventB->id]);

        $patron = Patron::factory()->make(['event_id' => $eventA->id]);
        $patron->table_id = $tableB->id;

        $this->expectException(ValidationException::class);
        $patron->save();
    }

    public function testPatronAcceptsTableFromSameEvent(): void
    {
        $event = $this->makeEvent();
        $table = Table::factory()->create(['event_id' => $event->id]);

        $patron = Patron::factory()->make(['event_id' => $event->id]);
        $patron->table_id = $table->id;
        $patron->save();

        $this->assertDatabaseHas('patrons', [
            'id' => $patron->id,
            'table_id' => $table->id,
        ]);
    }
}
