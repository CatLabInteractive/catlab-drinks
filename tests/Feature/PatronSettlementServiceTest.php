<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Services\PatronSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatronSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePatronWithOrders(int $unpaid, int $paid = 0): Patron
    {
        $organisation = Organisation::factory()->create();
        $event = Event::factory()->create([
            'organisation_id' => $organisation->id,
            'allow_unpaid_table_orders' => true,
        ]);
        $patron = Patron::factory()->create(['event_id' => $event->id]);

        for ($i = 0; $i < $unpaid; $i++) {
            $order = Order::factory()->make(['event_id' => $event->id]);
            $order->patron_id = $patron->id;
            $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
            $order->save();
        }

        for ($i = 0; $i < $paid; $i++) {
            $order = Order::factory()->make(['event_id' => $event->id]);
            $order->patron_id = $patron->id;
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->save();
        }

        return $patron;
    }

    public function testSettleMarksAllUnpaidOrdersPaid(): void
    {
        $patron = $this->makePatronWithOrders(3, 1);

        $settled = (new PatronSettlementService())->settle($patron, 'cash');

        $this->assertCount(3, $settled);
        $this->assertEquals(
            0,
            $patron->orders()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)->count()
        );

        foreach ($settled as $order) {
            $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
            $this->assertEquals('cash', $order->payment_type);
            $this->assertTrue((bool) $order->paid);
        }
    }

    public function testSettleIsIdempotent(): void
    {
        $patron = $this->makePatronWithOrders(2);
        $service = new PatronSettlementService();

        $first = $service->settle($patron, 'cash');
        $second = $service->settle($patron->refresh(), 'cash');

        $this->assertCount(2, $first);
        $this->assertCount(0, $second);
    }

    public function testSettleDoesNotTouchOtherPatrons(): void
    {
        $patronA = $this->makePatronWithOrders(1);
        $patronB = $this->makePatronWithOrders(1);

        (new PatronSettlementService())->settle($patronA, 'cash');

        $this->assertEquals(
            1,
            $patronB->orders()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)->count()
        );
    }

    public function testSettleRecordsDiscount(): void
    {
        $patron = $this->makePatronWithOrders(1);

        $settled = (new PatronSettlementService())->settle($patron, 'nfc', 25);

        $this->assertEquals(25, (int) $settled->first()->discount_percentage);
    }
}
