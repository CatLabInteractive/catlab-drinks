<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Event;
use App\Models\MenuHeading;
use App\Models\MenuItem;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Headings: titles that group products on the order screens.
 */
class MenuHeadingTest extends TestCase
{
	use RefreshDatabase;

	private Organisation $organisation;
	private Organisation $otherOrganisation;
	private User $user;
	private Event $event;
	private Event $otherEvent;
	private Event $foreignEvent;

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
			'is_selling' => true,
			'allow_unpaid_online_orders' => true,
			'order_token_secret' => null,
		];
		$this->event = Event::factory()->create($attributes + ['organisation_id' => $this->organisation->id]);
		$this->otherEvent = Event::factory()->create($attributes + ['organisation_id' => $this->organisation->id]);
		$this->foreignEvent = Event::factory()->create($attributes + ['organisation_id' => $this->otherOrganisation->id]);
	}

	public function testUserCanCreateAHeadingAndItGoesLast(): void
	{
		Passport::actingAs($this->user);
		$first = $this->postJson($this->indexUrl(), ['name' => 'Bieren'])->assertSuccessful();
		$second = $this->postJson($this->indexUrl(), ['name' => 'Frisdrank'])->assertSuccessful();
		$this->assertEquals(1, MenuHeading::find($first->json('id'))->position);
		$this->assertEquals(2, MenuHeading::find($second->json('id'))->position);

		$index = $this->getJson($this->indexUrl() . '?records=1000')->assertStatus(200);
		$this->assertEquals(['Bieren', 'Frisdrank'], array_column($index->json('items'), 'name'));
		$this->assertEquals([1, 2], array_column($index->json('items'), 'position'));
	}

	public function testHeadingNameIsRequired(): void
	{
		Passport::actingAs($this->user);
		// Charon answers 400 to a body without any field, so send a body that lacks only the name.
		$response = $this->postJson($this->indexUrl(), ['position' => 3])->assertStatus(422);
		$this->assertNotNull($response->json('error.status'));
		$this->assertNotNull($response->json('error.message'));
		$this->assertEquals(0, MenuHeading::count());
	}

	public function testUserCanRenameAHeading(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/headings/' . $heading->id, ['name' => 'Bier'])->assertStatus(200);
		$this->assertEquals('Bier', $heading->fresh()->name);
	}

	public function testHeadingIndexIsSortedByPositionThenId(): void
	{
		$a = $this->makeHeading($this->event, 'A');
		$b = $this->makeHeading($this->event, 'B');
		$c = $this->makeHeading($this->event, 'C');
		$a->forceFill(['position' => 2])->save();
		$b->forceFill(['position' => 1])->save();
		$c->forceFill(['position' => 1])->save();
		Passport::actingAs($this->user);
		$response = $this->getJson($this->indexUrl() . '?records=1000')->assertStatus(200);
		$this->assertEquals([$b->id, $c->id, $a->id], array_column($response->json('items'), 'id'));
	}

	public function testHeadingsDeniedForOtherOrganisation(): void
	{
		$heading = $this->makeHeading($this->foreignEvent, 'X');
		Passport::actingAs($this->user);
		$this->getJson($this->indexUrl($this->foreignEvent) . '?records=1000')->assertStatus(403);
		$this->postJson($this->indexUrl($this->foreignEvent), ['name' => 'Y'])->assertStatus(403);
		$this->putJson('/api/v1/headings/' . $heading->id, ['name' => 'Y'])->assertStatus(403);
		$this->deleteJson('/api/v1/headings/' . $heading->id)->assertStatus(403);
		$this->assertEquals('X', $heading->fresh()->name);
		$this->assertNull($heading->fresh()->deleted_at);
	}

	public function testUserOfADifferentOrganisationCannotViewAHeading(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$stranger = User::query()->create([
			'name' => 'Stranger',
			'email' => 'stranger-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$this->otherOrganisation->users()->attach($stranger->id);
		Passport::actingAs($stranger);
		$this->getJson('/api/v1/headings/' . $heading->id)->assertStatus(403);
	}

	public function testDeviceOfADifferentOrganisationCannotReadHeadings(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$auth = ['Authorization' => 'Bearer ' . $this->createDeviceToken($this->otherOrganisation)];
		$this->withHeaders($auth)->getJson('/pos-api/v1/events/' . $this->event->id . '/headings?records=1000')->assertStatus(403);
		$this->withHeaders($auth)->getJson('/pos-api/v1/headings/' . $heading->id)->assertStatus(403);
	}

	public function testHeadingNameLongerThan255CharactersIsRejected(): void
	{
		Passport::actingAs($this->user);
		$response = $this->postJson($this->indexUrl(), ['name' => str_repeat('a', 256)])->assertStatus(422);
		$this->assertEquals(422, $response->json('error.status'));
		$this->assertNotNull($response->json('error.message'));
		$this->assertEquals(0, MenuHeading::count());

		$heading = $this->makeHeading($this->event, 'Bieren');
		$this->putJson('/api/v1/headings/' . $heading->id, ['name' => str_repeat('b', 256)])
			->assertStatus(422)->assertJsonPath('error.status', 422);
		$this->assertEquals('Bieren', $heading->fresh()->name);

		$this->postJson($this->indexUrl(), ['name' => str_repeat('c', 255)])->assertSuccessful();
	}

	public function testDeviceCanReadHeadings(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$this->makeHeading($this->event, 'Frisdrank');
		$auth = ['Authorization' => 'Bearer ' . $this->createDeviceToken()];
		$response = $this->withHeaders($auth)->getJson('/pos-api/v1/events/' . $this->event->id . '/headings?records=1000')->assertStatus(200);
		$this->assertEquals(['Bieren', 'Frisdrank'], array_column($response->json('items'), 'name'));
		$this->withHeaders($auth)->getJson('/pos-api/v1/headings/' . $heading->id)->assertStatus(200)->assertJson(['name' => 'Bieren']);
	}

	public function testDeviceCannotWriteHeadings(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$auth = ['Authorization' => 'Bearer ' . $this->createDeviceToken()];
		$this->assertContains($this->withHeaders($auth)->postJson('/pos-api/v1/events/' . $this->event->id . '/headings', ['name' => 'New'])->status(), [403, 404, 405]);
		$this->assertContains($this->withHeaders($auth)->putJson('/pos-api/v1/headings/' . $heading->id, ['name' => 'Changed'])->status(), [403, 404, 405]);
		$this->assertContains($this->withHeaders($auth)->deleteJson('/pos-api/v1/headings/' . $heading->id)->status(), [403, 404, 405]);
		$this->assertEquals(1, MenuHeading::count());
		$this->assertEquals('Bieren', $heading->fresh()->name);
		$this->assertNull($heading->fresh()->deleted_at);
	}

	public function testMenuItemCarriesItsHeading(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$this->makeItem($this->event, 'Pils', $heading);

		Passport::actingAs($this->user);
		$management = $this->getJson('/api/v1/events/' . $this->event->id . '/menu?records=1000')->assertStatus(200);
		$this->assertEquals('Bieren', $management->json('items.0.heading.name'));
		$this->assertEquals(1, $management->json('items.0.heading.position'));
		$this->assertEquals($heading->id, $management->json('items.0.heading.id'));

		$device = $this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken())
			->getJson('/pos-api/v1/events/' . $this->event->id . '/menu?records=1000')->assertStatus(200);
		$this->assertEquals('Bieren', $device->json('items.0.heading.name'));
		$this->assertEquals(1, $device->json('items.0.heading.position'));

		$public = $this->withHeader('X-Event-Token', $this->event->order_token)
			->getJson('/api/v1/public/menu.json?records=1000')->assertStatus(200);
		$this->assertEquals('Bieren', $public->json('items.0.heading.name'));
		$this->assertEquals(1, $public->json('items.0.heading.position'));
	}

	public function testMenuItemWithoutHeadingHasNullHeading(): void
	{
		$this->makeItem($this->event, 'Pils');
		Passport::actingAs($this->user);
		$response = $this->getJson('/api/v1/events/' . $this->event->id . '/menu?records=1000')->assertStatus(200);
		// Charon leaves an empty relationship out of the output.
		$this->assertNull($response->json('items.0.heading'));
	}

	public function testUserCanSetAndClearTheHeadingOfAProduct(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$item = $this->makeItem($this->event, 'Pils');
		$item->forceFill(['description' => '25cl', 'vat_percentage' => 21])->save();
		Passport::actingAs($this->user);

		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 3, 'heading' => ['id' => $heading->id]])->assertStatus(200);
		$this->assertEquals($heading->id, $item->fresh()->heading_id);

		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 3, 'heading' => null])->assertStatus(200);
		$item->refresh();
		$this->assertNull($item->heading_id);
		$this->assertEquals('25cl', $item->description);
		$this->assertEquals(21, $item->vat_percentage);
		$this->assertEquals(3, $item->price);
	}

	public function testUpdateWithoutHeadingKeyKeepsTheHeading(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$item = $this->makeItem($this->event, 'Pils', $heading);
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 3])->assertStatus(200);
		$this->assertEquals($heading->id, $item->fresh()->heading_id);
	}

	public function testBulkCreateAcceptsAHeading(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		Passport::actingAs($this->user);
		$response = $this->withHeader('X-Bulk-Request', '1')->postJson($this->menuUrl(), [
			'items' => [
				['name' => 'One', 'price' => 1, 'heading' => ['id' => $heading->id]],
				['name' => 'Two', 'price' => 2],
			],
		]);
		$response->assertSuccessful();
		$this->assertEquals($heading->id, MenuItem::find($response->json('items.0.id'))->heading_id);
		$this->assertNull(MenuItem::find($response->json('items.1.id'))->heading_id);
	}

	public function testHeadingOfADifferentEventIsRejected(): void
	{
		$heading = $this->makeHeading($this->otherEvent, 'Elsewhere');
		$this->assertLinkIsRejected('heading', $heading->id, 'heading_id');
	}

	public function testHeadingOfADifferentOrganisationIsRejected(): void
	{
		$heading = $this->makeHeading($this->foreignEvent, 'Foreign');
		$this->assertLinkIsRejected('heading', $heading->id, 'heading_id');
	}

	public function testCategoryOfADifferentEventIsRejected(): void
	{
		$category = Category::factory()->create(['event_id' => $this->otherEvent->id]);
		$this->assertLinkIsRejected('category', $category->id, 'category_id');
	}

	public function testDeletedHeadingIsRejected(): void
	{
		$heading = $this->makeHeading($this->event, 'Gone');
		$heading->delete();
		// Charon answers 404 when the linked row does not exist or is soft deleted.
		$this->assertLinkIsRejected('heading', $heading->id, 'heading_id', 404);
	}

	public function testBulkCreateRejectsAHeadingOfADifferentEvent(): void
	{
		$heading = $this->makeHeading($this->otherEvent, 'Elsewhere');
		Passport::actingAs($this->user);
		$this->withHeader('X-Bulk-Request', '1')->postJson($this->menuUrl(), [
			'items' => [['name' => 'One', 'price' => 1, 'heading' => ['id' => $heading->id]]],
		])->assertStatus(422);
		$this->assertEquals(0, MenuItem::count());
	}

	public function testProductWithADeletedCategoryCanStillBeSavedByADevice(): void
	{
		$category = Category::factory()->create(['event_id' => $this->event->id]);
		$item = $this->makeItem($this->event, 'Pils');
		$item->forceFill(['category_id' => $category->id])->save();
		$category->delete();
		$this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken())
			->putJson('/pos-api/v1/menuitems/' . $item->id, ['is_selling' => false])->assertStatus(200);
		$this->assertFalse((bool) $item->fresh()->is_selling);
	}

	public function testProductWithADeletedCategoryCanStillBeEditedByAUser(): void
	{
		$category = Category::factory()->create(['event_id' => $this->event->id]);
		$item = $this->makeItem($this->event, 'Pils');
		$item->forceFill(['category_id' => $category->id])->save();
		$category->delete();
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 4])->assertStatus(200);
		$this->assertEquals(4, $item->fresh()->price);
	}

	public function testProductWithACategoryOfAnotherEventCanStillBeEdited(): void
	{
		$legacy = Category::factory()->create(['event_id' => $this->otherEvent->id]);
		$other = Category::factory()->create(['event_id' => $this->otherEvent->id]);
		$item = $this->makeItem($this->event, 'Pils');
		$item->forceFill(['category_id' => $legacy->id])->save();
		Passport::actingAs($this->user);
		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 4])->assertStatus(200);
		$this->assertEquals($legacy->id, $item->fresh()->category_id);
		$this->putJson('/api/v1/menuitems/' . $item->id, ['name' => 'Pils', 'price' => 4, 'category' => ['id' => $other->id]])->assertStatus(422);
		$this->assertEquals($legacy->id, $item->fresh()->category_id);
	}

	public function testDeviceCannotChangeTheHeadingOfAProduct(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$item = $this->makeItem($this->event, 'Pils');
		$this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken())
			->putJson('/pos-api/v1/menuitems/' . $item->id, ['heading' => ['id' => $heading->id], 'is_selling' => false])
			->assertStatus(200);
		$this->assertNull($item->fresh()->heading_id);
		$this->assertFalse((bool) $item->fresh()->is_selling);
	}

	public function testDeletingAHeadingClearsItsProducts(): void
	{
		$heading = $this->makeHeading($this->event, 'Bieren');
		$a = $this->makeItem($this->event, 'A', $heading);
		$b = $this->makeItem($this->event, 'B', $heading);
		$trashed = $this->makeItem($this->event, 'T', $heading);
		$trashed->delete();
		Passport::actingAs($this->user);
		$this->deleteJson('/api/v1/headings/' . $heading->id)->assertSuccessful();
		$this->assertNull($a->fresh()->heading_id);
		$this->assertNull($b->fresh()->heading_id);
		$this->assertNull(MenuItem::withTrashed()->find($trashed->id)->heading_id);
		$this->assertNotNull($a->fresh());
		$this->assertNotNull(MenuHeading::withTrashed()->find($heading->id)->deleted_at);
	}

	public function testNewHeadingPositionCountsSoftDeletedHeadings(): void
	{
		$this->makeHeading($this->event, 'A');
		$last = $this->makeHeading($this->event, 'B');
		$last->delete();
		Passport::actingAs($this->user);
		$response = $this->postJson($this->indexUrl(), ['name' => 'C'])->assertSuccessful();
		$this->assertGreaterThan($last->position, MenuHeading::find($response->json('id'))->position);
	}

	/**
	 * PUT and POST with a link to a row of the wrong event give 422 (or the given status) and write nothing.
	 */
	private function assertLinkIsRejected(string $property, int $id, string $column, int $status = 422): void
	{
		$item = $this->makeItem($this->event, 'Pils');
		Passport::actingAs($this->user);

		$response = $this->putJson('/api/v1/menuitems/' . $item->id, [
			'name' => 'Pils', 'price' => 3, $property => ['id' => $id],
		])->assertStatus($status);
		$this->assertNotNull($response->json('error.message'));
		$this->assertNull($item->fresh()->$column);
		$this->assertEquals(2.5, (float) $item->fresh()->price);

		$count = MenuItem::count();
		$this->postJson($this->menuUrl(), ['name' => 'New', 'price' => 1, $property => ['id' => $id]])->assertStatus($status);
		$this->assertEquals($count, MenuItem::count());
	}

	public function testSequenceEndpointStoresTheHeadingSequence(): void
	{
		[$a, $b, $c] = $this->makeHeadings($this->event, ['A', 'B', 'C']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $c->id], ['id' => $a->id], ['id' => $b->id]]])
			->assertStatus(200)->assertJson(['success' => true]);
		$this->assertEquals([2, 3, 1], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position]);
	}

	public function testHeadingsMissingFromTheListGoAfterTheListedOnes(): void
	{
		[$a, $b, $c, $d] = $this->makeHeadings($this->event, ['A', 'B', 'C', 'D']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $d->id], ['id' => $b->id]]])->assertStatus(200);
		$this->assertEquals([3, 2, 4, 1], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position, $d->fresh()->position]);
	}

	public function testHeadingSequenceCountsSoftDeletedHeadings(): void
	{
		[$a, $b, $c] = $this->makeHeadings($this->event, ['A', 'B', 'C']);
		$b->delete();
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $c->id]]])->assertStatus(200);
		$this->assertEquals([2, 3, 1], [
			$a->fresh()->position,
			MenuHeading::withTrashed()->find($b->id)->position,
			$c->fresh()->position,
		]);
	}

	public function testHeadingSequenceRejectsIdsOfADifferentEvent(): void
	{
		[$a, $b] = $this->makeHeadings($this->event, ['A', 'B']);
		[$x] = $this->makeHeadings($this->otherEvent, ['X']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $b->id], ['id' => $x->id], ['id' => $a->id]]])
			->assertStatus(422);
		$this->assertEquals([1, 2, 1], [$a->fresh()->position, $b->fresh()->position, $x->fresh()->position]);
	}

	public function testHeadingSequenceRejectsDuplicateIds(): void
	{
		[$a, $b] = $this->makeHeadings($this->event, ['A', 'B']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $a->id], ['id' => $a->id], ['id' => $b->id]]])
			->assertStatus(422);
		$this->assertEquals([1, 2], [$a->fresh()->position, $b->fresh()->position]);
	}

	public function testHeadingSequenceRejectsADeletedHeading(): void
	{
		[$a, $b] = $this->makeHeadings($this->event, ['A', 'B']);
		$b->delete();
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl(), ['items' => [['id' => $a->id], ['id' => $b->id]]])
			->assertStatus(422);
		$this->assertEquals(1, $a->fresh()->position);
	}

	public function testHeadingSequenceRejectsABodyWithoutItems(): void
	{
		[$a] = $this->makeHeadings($this->event, ['A']);
		Passport::actingAs($this->user);
		$response = $this->putJson($this->sequenceUrl(), [])->assertStatus(422);
		$this->assertNotNull($response->json('error.status'));
		$this->assertNotNull($response->json('error.message'));
		$this->putJson($this->sequenceUrl(), ['items' => 'x'])->assertStatus(422);
		$this->assertEquals(1, $a->fresh()->position);
	}

	public function testHeadingSequenceDeniedForOtherOrganisation(): void
	{
		[$x] = $this->makeHeadings($this->foreignEvent, ['X']);
		Passport::actingAs($this->user);
		$this->putJson($this->sequenceUrl($this->foreignEvent), ['items' => [['id' => $x->id]]])->assertStatus(403);
	}

	public function testHeadingSequenceDeniedForDevices(): void
	{
		[$a] = $this->makeHeadings($this->event, ['A']);
		$status = $this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken())
			->putJson('/pos-api/v1/events/' . $this->event->id . '/headings/sequence', ['items' => [['id' => $a->id]]])
			->status();
		$this->assertContains($status, [404, 405]);
	}

	/**
	 * @param string[] $names
	 * @return MenuHeading[]
	 */
	private function makeHeadings(Event $event, array $names): array
	{
		return array_map(fn ($name) => $this->makeHeading($event, $name), $names);
	}

	private function sequenceUrl(?Event $event = null): string
	{
		return $this->indexUrl($event) . '/sequence';
	}

	private function indexUrl(?Event $event = null): string
	{
		return '/api/v1/events/' . ($event ?? $this->event)->id . '/headings';
	}

	private function menuUrl(): string
	{
		return '/api/v1/events/' . $this->event->id . '/menu';
	}

	private function makeHeading(Event $event, string $name): MenuHeading
	{
		return MenuHeading::factory()->create(['event_id' => $event->id, 'name' => $name]);
	}

	private function makeItem(Event $event, string $name, ?MenuHeading $heading = null): MenuItem
	{
		return MenuItem::factory()->create([
			'event_id' => $event->id,
			'category_id' => null,
			'heading_id' => $heading?->id,
			'name' => $name,
			'price' => 2.5,
		]);
	}

	private function createDeviceToken(?Organisation $organisation = null): string
	{
		$device = Device::factory()->create(['organisation_id' => ($organisation ?? $this->organisation)->id]);
		return $device->createAccessToken($this->user)->plainTextToken;
	}
}
