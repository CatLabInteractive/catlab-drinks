<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The position of menu items and the order in which menus are read.
 */
class MenuSequenceTest extends TestCase
{
	use RefreshDatabase;

	private Organisation $organisation;
	private Organisation $otherOrganisation;
	private User $user;
	private Event $event;
	private Event $otherEventOfSameOrganisation;

	protected function setUp(): void
	{
		parent::setUp();

		$this->organisation = Organisation::factory()->create();
		$this->otherOrganisation = Organisation::factory()->create();

		$this->user = User::query()->create([
			'name' => 'Member',
			'email' => 'member-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$this->organisation->users()->attach($this->user->id);

		$attributes = [
			'organisation_id' => $this->organisation->id,
			'is_selling' => true,
			'allow_unpaid_online_orders' => true,
			'order_token_secret' => null,
		];
		$this->event = Event::factory()->create($attributes);
		$this->otherEventOfSameOrganisation = Event::factory()->create($attributes);
	}

	public function testNewProductsGetTheNextPosition(): void
	{
		[$a, $b, $c] = $this->makeItems($this->event, ['A', 'B', 'C']);
		$this->assertEquals([1, 2, 3], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position]);
	}

	public function testPositionIsCountedPerEvent(): void
	{
		$this->makeItems($this->event, ['A', 'B']);
		[$x] = $this->makeItems($this->otherEventOfSameOrganisation, ['X']);
		$this->assertEquals(1, $x->fresh()->position);
	}

	public function testManagementIndexIsSortedByPosition(): void
	{
		$this->shuffleThreeItems();
		Passport::actingAs($this->user);
		$response = $this->getJson('/api/v1/events/' . $this->event->id . '/menu?records=1000')->assertStatus(200);
		$this->assertEquals(['C', 'A', 'B'], array_column($response->json('items'), 'name'));
		$this->assertEquals(1, $response->json('items.0.position'));
	}

	public function testDeviceIndexIsSortedByPosition(): void
	{
		$this->shuffleThreeItems();
		$response = $this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken($this->organisation))
			->getJson('/pos-api/v1/events/' . $this->event->id . '/menu?records=1000')
			->assertStatus(200);
		$this->assertEquals(['C', 'A', 'B'], array_column($response->json('items'), 'name'));
		$this->assertEquals(1, $response->json('items.0.position'));
	}

	public function testPublicMenuIsSortedByPosition(): void
	{
		$this->shuffleThreeItems();
		$response = $this->withHeader('X-Event-Token', $this->event->order_token)
			->getJson('/api/v1/public/menu.json?records=1000')
			->assertStatus(200);
		$this->assertEquals(['C', 'A', 'B'], array_column($response->json('items'), 'name'));
	}

	public function testEqualPositionsFallBackToId(): void
	{
		[$a, $b, $c] = $this->makeItems($this->event, ['C', 'A', 'B']);
		foreach ([$a, $b, $c] as $item) {
			$item->forceFill(['position' => 0])->save();
		}
		Passport::actingAs($this->user);
		$response = $this->getJson('/api/v1/events/' . $this->event->id . '/menu?records=1000')->assertStatus(200);
		$this->assertEquals(['C', 'A', 'B'], array_column($response->json('items'), 'name'));
	}

	public function testDeviceCannotWritePosition(): void
	{
		[$a] = $this->makeItems($this->event, ['A']);
		$this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken($this->organisation))
			->putJson('/pos-api/v1/menuitems/' . $a->id, ['position' => 9, 'is_selling' => false])
			->assertStatus(200);
		$this->assertEquals(1, $a->fresh()->position);
		$this->assertFalse((bool) $a->fresh()->is_selling);
	}

	public function testUserCanWritePositionOnCreate(): void
	{
		Passport::actingAs($this->user);
		$response = $this->postJson('/api/v1/events/' . $this->event->id . '/menu', ['name' => 'Z', 'price' => 2, 'position' => 7]);
		$response->assertSuccessful();
		$this->assertEquals(7, MenuItem::find($response->json('id'))->position);
	}

	private function shuffleThreeItems(): void
	{
		[$a, $b, $c] = $this->makeItems($this->event, ['A', 'B', 'C']);
		$c->forceFill(['position' => 1])->save();
		$a->forceFill(['position' => 2])->save();
		$b->forceFill(['position' => 3])->save();
	}

	/**
	 * @param string[] $names
	 * @return MenuItem[]
	 */
	private function makeItems(Event $event, array $names): array
	{
		$items = [];
		foreach ($names as $name) {
			$items[] = MenuItem::factory()->create([
				'event_id' => $event->id,
				'category_id' => null,
				'name' => $name,
			]);
		}
		return $items;
	}

	private function createDeviceToken(Organisation $organisation): string
	{
		$device = Device::factory()->create(['organisation_id' => $organisation->id]);
		return $device->createAccessToken($this->user)->plainTextToken;
	}
}
