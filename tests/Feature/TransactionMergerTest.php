<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\Organisation;
use App\Models\Transaction;
use App\Models\User;
use App\Tools\TransactionMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Integration tests for TransactionMerger covering offline POS scenarios.
 * Tests that transactions from different POS terminals merge correctly
 * when they arrive in different order or when some terminals are offline.
 */
class TransactionMergerTest extends TestCase
{
	use RefreshDatabase;

	private Organisation $organisation;
	private User $user;

	protected function setUp(): void
	{
		parent::setUp();

		$this->organisation = Organisation::factory()->create();
		$this->user = User::query()->create([
			'name' => 'Test User',
			'email' => 'test-' . Str::random(8) . '@example.com',
			'password' => bcrypt('secret'),
		]);
		$this->organisation->users()->attach($this->user->id);
	}

	private function createCard(string $uid = null): Card
	{
		$card = new Card();
		$card->uid = $uid ?? 'card-' . Str::random(8);
		$card->name = 'Test Card';
		$card->organisation_id = $this->organisation->id;
		$card->transaction_count = 0;
		$card->save();
		return $card;
	}

	private function makeTransaction(string $cardUid, int $syncId, int $value, string $type = 'topup'): Transaction
	{
		$tx = new Transaction();
		$tx->card_uid = $cardUid;
		$tx->card_sync_id = $syncId;
		$tx->value = $value;
		$tx->transaction_type = $type;
		$tx->has_synced = true;
		return $tx;
	}

	/**
	 * Test basic merge: single transaction merges correctly.
	 */
	public function testSingleTransactionMerge(): void
	{
		$card = $this->createCard('card-001');

		$merger = new TransactionMerger($this->organisation);
		$transactions = $merger->mergeTransactions([
			$this->makeTransaction('card-001', 1, 500, 'topup'),
		]);

		$this->assertCount(1, $transactions);
		$this->assertEquals(500, $transactions[0]->value);
		$this->assertEquals(1, $transactions[0]->card_sync_id);
	}

	/**
	 * Test multiple transactions merge in correct order.
	 */
	public function testMultipleTransactionsInOrder(): void
	{
		$card = $this->createCard('card-002');

		$merger = new TransactionMerger($this->organisation);
		$transactions = $merger->mergeTransactions([
			$this->makeTransaction('card-002', 1, 1000, 'topup'),
			$this->makeTransaction('card-002', 2, -300, 'sale'),
			$this->makeTransaction('card-002', 3, -200, 'sale'),
		]);

		$this->assertCount(3, $transactions);
		$this->assertEquals(1000, $transactions[0]->value);
		$this->assertEquals(-300, $transactions[1]->value);
		$this->assertEquals(-200, $transactions[2]->value);
	}

	/**
	 * Test that merging the same transaction twice doesn't duplicate it.
	 */
	public function testDuplicateTransactionMerge(): void
	{
		$card = $this->createCard('card-003');

		$merger1 = new TransactionMerger($this->organisation);
		$merger1->mergeTransactions([
			$this->makeTransaction('card-003', 1, 500, 'topup'),
		]);

		// Same transaction again (e.g. from offline sync)
		$merger2 = new TransactionMerger($this->organisation);
		$merger2->mergeTransactions([
			$this->makeTransaction('card-003', 1, 500, 'topup'),
		]);

		$card->refresh();
		$totalTransactions = $card->transactions()->where('card_sync_id', '!=', Transaction::ID_OVERFLOW)->count();
		$this->assertEquals(1, $totalTransactions);
	}

	/**
	 * Test out-of-order transactions: POS B sends tx 3 before POS A sends tx 1 and 2.
	 */
	public function testOutOfOrderTransactions(): void
	{
		$card = $this->createCard('card-004');

		// POS B sends transaction 3 first
		$merger1 = new TransactionMerger($this->organisation);
		$merger1->mergeTransactions([
			$this->makeTransaction('card-004', 3, -200, 'sale'),
		]);

		// POS A sends transactions 1 and 2 later
		$merger2 = new TransactionMerger($this->organisation);
		$merger2->mergeTransactions([
			$this->makeTransaction('card-004', 1, 1000, 'topup'),
			$this->makeTransaction('card-004', 2, -300, 'sale'),
		]);

		$card->refresh();
		$txCount = $card->transactions()->where('card_sync_id', '!=', Transaction::ID_OVERFLOW)->count();
		$this->assertEquals(3, $txCount);
	}

	/**
	 * Test that transactions from multiple cards don't interfere.
	 */
	public function testMultipleCardsMerge(): void
	{
		$card1 = $this->createCard('card-multi-1');
		$card2 = $this->createCard('card-multi-2');

		$merger = new TransactionMerger($this->organisation);
		$transactions = $merger->mergeTransactions([
			$this->makeTransaction('card-multi-1', 1, 500, 'topup'),
			$this->makeTransaction('card-multi-2', 1, 1000, 'topup'),
			$this->makeTransaction('card-multi-1', 2, -100, 'sale'),
		]);

		$this->assertCount(3, $transactions);

		$card1TxCount = $card1->transactions()->where('card_sync_id', '!=', Transaction::ID_OVERFLOW)->count();
		$card2TxCount = $card2->transactions()->where('card_sync_id', '!=', Transaction::ID_OVERFLOW)->count();
		$this->assertEquals(2, $card1TxCount);
		$this->assertEquals(1, $card2TxCount);
	}

	/**
	 * Test the merge-transactions API endpoint via Device API.
	 */
	public function testMergeTransactionsEndpoint(): void
	{
		$device = Device::factory()->create([
			'organisation_id' => $this->organisation->id,
		]);

		$card = $this->createCard('card-api-001');

		$token = new DeviceAccessToken([
			'device_id' => $device->id,
			'access_token' => 'merge-test-token',
			'expires_at' => now()->addHour(),
		]);
		$token->created_by = $this->user->id;
		$token->save();

		$response = $this
			->withHeader('Authorization', 'Bearer ' . $token->access_token)
			->postJson('/pos-api/v1/organisations/' . $this->organisation->id . '/merge-transactions', [
				'items' => [
					[
						'card' => 'card-api-001',
						'card_transaction' => 1,
						'value' => 500,
						'type' => 'topup',
						'has_synced' => true,
					],
				],
			]);

		$response->assertStatus(200);
	}

	/**
	 * Test that the organisation transactions endpoint works via Management API.
	 */
	public function testOrganisationTransactionsEndpoint(): void
	{
		$card = $this->createCard('card-org-001');

		// Create a transaction directly
		$tx = new Transaction();
		$tx->card_id = $card->id;
		$tx->card_sync_id = 1;
		$tx->value = 500;
		$tx->transaction_type = 'topup';
		$tx->has_synced = true;
		$tx->save();

		Passport::actingAs($this->user);

		$response = $this
			->getJson('/api/v1/organisations/' . $this->organisation->id . '/transactions');

		$response->assertStatus(200);
	}

	/**
	 * Test card transactions index via Management API.
	 */
	public function testCardTransactionsEndpoint(): void
	{
		$card = $this->createCard('card-tx-001');

		$tx = new Transaction();
		$tx->card_id = $card->id;
		$tx->card_sync_id = 1;
		$tx->value = 1000;
		$tx->transaction_type = 'topup';
		$tx->has_synced = true;
		$tx->save();

		Passport::actingAs($this->user);

		$response = $this
			->getJson('/api/v1/cards/' . $card->id . '/transactions');

		$response->assertStatus(200);
	}
}
