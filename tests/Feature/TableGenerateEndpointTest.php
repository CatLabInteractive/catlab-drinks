<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organisation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * HTTP coverage for POST /api/v1/events/{id}/tables/generate:
 * authorization and the 1-100 count clamp.
 */
class TableGenerateEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $organisation = Organisation::factory()->create();
        $this->event = Event::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
    }

    private function actAsMember(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->event->organisation->users()->attach($user->id);

        Passport::actingAs($user);
    }

    private function generate(int $count)
    {
        return $this->postJson(
            '/api/v1/events/' . $this->event->id . '/tables/generate',
            ['count' => $count]
        );
    }

    public function testGeneratesRequestedNumberOfTables(): void
    {
        $this->actAsMember();

        $this->generate(5)->assertStatus(200);

        $this->assertEquals(5, $this->event->tables()->count());
        $this->assertEquals(
            [1, 2, 3, 4, 5],
            $this->event->tables()->orderBy('table_number')
                ->pluck('table_number')->map(fn ($n) => (int) $n)->all()
        );
    }

    public function testCountIsClampedToAtLeastOne(): void
    {
        $this->actAsMember();

        $this->generate(0)->assertStatus(200);

        $this->assertEquals(1, $this->event->tables()->count());
    }

    public function testCountIsClampedToAtMostOneHundred(): void
    {
        $this->actAsMember();

        $this->generate(500)->assertStatus(200);

        $this->assertEquals(100, $this->event->tables()->count());
    }

    public function testContinuesFromHighestActiveNumber(): void
    {
        $this->actAsMember();

        Table::factory()->create([
            'event_id' => $this->event->id,
            'table_number' => 7,
        ]);

        $this->generate(2)->assertStatus(200);

        $this->assertEquals(
            [7, 8, 9],
            $this->event->tables()->orderBy('table_number')
                ->pluck('table_number')->map(fn ($n) => (int) $n)->all()
        );
    }

    public function testUserFromOtherOrganisationCannotGenerate(): void
    {
        $outsider = User::query()->create([
            'name' => 'Outsider',
            'email' => 'outsider-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        Organisation::factory()->create()->users()->attach($outsider->id);

        Passport::actingAs($outsider);

        $this->generate(3)->assertStatus(403);
        $this->assertEquals(0, $this->event->tables()->count());
    }
}
