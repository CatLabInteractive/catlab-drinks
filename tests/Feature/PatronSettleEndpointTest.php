<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * HTTP-level coverage for the atomic patron settle endpoint
 * (POST /api/v1/patrons/{id}/settle), which is a money-touching path.
 */
class PatronSettleEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function makePatronWithUnpaidOrders(Organisation $organisation, int $unpaid = 2): Patron
    {
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

        return $patron;
    }

    public function testAuthorizedPrincipalSettlesPatronOrders(): void
    {
        $organisation = Organisation::factory()->create();
        $user = $this->makeUser();
        $organisation->users()->attach($user->id);

        $patron = $this->makePatronWithUnpaidOrders($organisation, 2);

        Passport::actingAs($user);
        $response = $this
            ->postJson('/api/v1/patrons/' . $patron->id . '/settle', [
                'payment_type' => 'cash',
            ]);

        $response->assertStatus(200);

        foreach ($patron->orders()->get() as $order) {
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid' => true,
            ]);
        }
    }

    public function testPrincipalFromDifferentOrganisationIsForbidden(): void
    {
        $organisation = Organisation::factory()->create();
        $otherOrganisation = Organisation::factory()->create();
        $otherUser = $this->makeUser();
        $otherOrganisation->users()->attach($otherUser->id);

        $patron = $this->makePatronWithUnpaidOrders($organisation, 2);
        $orderIds = $patron->orders()->pluck('id');

        Passport::actingAs($otherUser);
        $response = $this
            ->postJson('/api/v1/patrons/' . $patron->id . '/settle', [
                'payment_type' => 'cash',
            ]);

        $response->assertStatus(403);

        foreach ($orderIds as $orderId) {
            $this->assertDatabaseHas('orders', [
                'id' => $orderId,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                'paid' => false,
            ]);
        }
    }
}
