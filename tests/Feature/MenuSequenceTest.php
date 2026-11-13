<?php

namespace Tests\Feature;

use App\Models\Category;
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

	public function testSequenceEndpointStoresTheSequence(): void
	{
		[$a, $b, $c] = $this->makeItems($this->event, ['A', 'B', 'C']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $c->id], ['id' => $a->id], ['id' => $b->id]]])
			->assertStatus(200)->assertJson(['success' => true]);
		$this->assertEquals([2, 3, 1], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position]);
	}

	public function testProductsMissingFromTheListGoAfterTheListedOnes(): void
	{
		[$a, $b, $c, $d] = $this->makeItems($this->event, ['A', 'B', 'C', 'D']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $d->id], ['id' => $b->id]]])->assertStatus(200);
		// listed: D=1, B=2. Not listed keep their relative sequence: A=3, C=4.
		$this->assertEquals([3, 2, 4, 1], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position, $d->fresh()->position]);
	}

	public function testSequenceRejectsIdsOfADifferentEvent(): void
	{
		[$a, $b] = $this->makeItems($this->event, ['A', 'B']);
		[$x] = $this->makeItems($this->otherEventOfSameOrganisation, ['X']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $b->id], ['id' => $x->id], ['id' => $a->id]]])
			->assertStatus(422);
		$this->assertEquals([1, 2, 1], [$a->fresh()->position, $b->fresh()->position, $x->fresh()->position]);
	}

	public function testSequenceRejectsDuplicateIds(): void
	{
		[$a, $b] = $this->makeItems($this->event, ['A', 'B']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $a->id], ['id' => $a->id], ['id' => $b->id]]])
			->assertStatus(422);
		$this->assertEquals([1, 2], [$a->fresh()->position, $b->fresh()->position]);
	}

	public function testSequenceRejectsADeletedProduct(): void
	{
		[$a, $b] = $this->makeItems($this->event, ['A', 'B']);
		$b->delete();
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $a->id], ['id' => $b->id]]])
			->assertStatus(422);
	}

	public function testSequenceRejectsABodyWithoutItems(): void
	{
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), [])->assertStatus(422);
		$this->putJson($this->sequenceUrl(), ['items' => 'x'])->assertStatus(422);
	}

	public function testSequenceDeniedForOtherOrganisation(): void
	{
		$otherEvent = Event::factory()->create([
			'organisation_id' => $this->otherOrganisation->id,
			'is_selling' => true,
			'allow_unpaid_online_orders' => true,
			'order_token_secret' => null,
		]);
		[$x] = $this->makeItems($otherEvent, ['X']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl($otherEvent), ['items' => [['id' => $x->id]]])->assertStatus(403);
	}

	public function testSequenceDeniedForDevices(): void
	{
		$status = $this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken($this->organisation))
			->putJson('/pos-api/v1/events/' . $this->event->id . '/menu/sequence', ['items' => []])
			->status();
		$this->assertContains($status, [404, 405]);
	}

	public function testSequenceErrorUsesTheCommonErrorFormat(): void
	{
		Passport::actingAs($this->user);
		$response = $this->putJson($this->sequenceUrl(), [])->assertStatus(422);
		$this->assertNotNull($response->json('error.status'));
		$this->assertNotNull($response->json('error.message'));
	}

	public function testBulkCreateReturnsIdsInRequestOrder(): void
	{
		Passport::actingAs($this->user);
		$response = $this->withHeader('X-Bulk-Request', '1')->postJson('/api/v1/events/' . $this->event->id . '/menu', [
			'items' => [
				['name' => 'One', 'price' => 1, 'vat_percentage' => 21],
				['name' => 'Two', 'price' => 2, 'description' => 'second'],
			],
		]);
		$response->assertSuccessful();
		$this->assertEquals(['One', 'Two'], array_column($response->json('items'), 'name'));
		$this->assertNotNull($response->json('items.0.id'));
		$this->assertEquals(21, MenuItem::find($response->json('items.0.id'))->vat_percentage);
		$this->assertTrue((bool) MenuItem::find($response->json('items.1.id'))->is_selling);
	}

	public function testUpdateWithNameAndPriceKeepsOtherFields(): void
	{
		[$category, $item] = $this->makeDetailedItem();
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 3])->assertStatus(200);
		$item->refresh();
		$this->assertEquals(3, $item->price);
		$this->assertEquals('Pils', $item->name);
		$this->assertEquals('25cl', $item->description);
		$this->assertEquals(21, $item->vat_percentage);
		$this->assertEquals($category->id, $item->category_id);
		$this->assertFalse((bool) $item->is_selling);
	}

	public function testUpdateCanClearCategoryDescriptionAndVat(): void
	{
		[, $item] = $this->makeDetailedItem();
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/menuitems/' . $item->id, [
			'name' => 'Pils', 'price' => 2.5, 'category' => null, 'description' => null, 'vat_percentage' => null,
		])->assertStatus(200);
		$item->refresh();
		$this->assertNull($item->category_id);
		$this->assertNull($item->description);
		$this->assertNull($item->vat_percentage);
		$this->assertFalse((bool) $item->is_selling);
	}

	public function testUpdateCanSetCategory(): void
	{
		$category = Category::factory()->create(['event_id' => $this->event->id]);
		[$item] = $this->makeItems($this->event, ['Pils']);
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/menuitems/' . $item->id, [
			'name' => 'Pils', 'price' => 2.5, 'category' => ['id' => $category->id],
		])->assertStatus(200);
		$this->assertEquals($category->id, $item->fresh()->category_id);
	}

	public function testUpdateWithoutNameIsRejected(): void
	{
		[$item] = $this->makeItems($this->event, ['Pils']);
		Passport::actingAs($this->user);
		$response = $this->putJson('/api/v1/menuitems/' . $item->id, ['price' => 3])->assertStatus(422);
		$this->assertNotNull($response->json('error.status'));
		$this->assertNotNull($response->json('error.message'));
	}

	/**
	 * @return array{0: Category, 1: MenuItem}
	 */
	private function makeDetailedItem(): array
	{
		$category = Category::factory()->create(['event_id' => $this->event->id]);
		$item = MenuItem::factory()->create([
			'event_id' => $this->event->id, 'category_id' => $category->id,
			'name' => 'Pils', 'description' => '25cl', 'price' => 2.5, 'vat_percentage' => 21, 'is_selling' => false,
		]);
		return [$category, $item];
	}

	private function sequenceUrl(?Event $event = null): string
	{
		return '/api/v1/events/' . ($event ?? $this->event)->id . '/menu/sequence';
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
