<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Tools;

use App\Exceptions\TransactionMergeException;
use App\Models\Card;
use App\Models\Organisation;
use App\Models\Transaction;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use CatLab\Charon\Exceptions\TranslatableErrorMessage;
use DB;

/**
 * Class TransactionMerger
 *
 * The transaction merger... merges transactions.
 * It does this without changing the last known card balance, so if transactions
 * are added that might change the sum of the transactions, the difference is taken from
 * a special 'temporary' transaction.
 *
 * @package App\Tools
 */
class TransactionMerger
{
    /**
     * @var Organisation
     */
    private $organisation;

    /**
     * @var Card[]
     */
    private $cards = [];

    public function __construct(Organisation $organisation)
    {
        $this->organisation = $organisation;
    }

    /**
     * @param Transaction[] $entities
     * @return array
     * @throws EntityNotFoundException
     * @throws \Throwable
     */
    public function mergeTransactions(array $entities)
    {
        $transactions = [];
        DB::transaction(function () use ($entities, &$transactions) {

            foreach ($entities as $entity) {
                $transaction = $this->mergeTransaction($entity);
                if ($transaction) {
                    $transactions[] = $transaction;
                }
            }

            // now fix the saldo
            $this->fixBalances();

        }, 5);

        return $transactions;
    }

    /**
     *
     */
    protected function fixBalances()
    {
        foreach ($this->cards as $card) {
            $this->fixBalance($card);
        }
    }

    /**
     * @param Card $card
     * @throws \Exception
     */
    protected function fixBalance(Card $card)
    {
        $originalBalance = $card->original_balance;

        // lets take a look at the current balance
        $currentBalance = $card->transactions()->sum('value');

        if (intval($originalBalance) !== intval($currentBalance)) {
            $overflowTransaction = $card->getOverflowTransaction();
            $overflowTransaction->value += $currentBalance - $originalBalance;
            $overflowTransaction->save();
        }
    }

    /**
     * @param Transaction $entity
     * @return Transaction
     * @throws EntityNotFoundException
     */
    protected function mergeTransaction(Transaction $entity)
    {
        // Load the card
        if (!$entity->card_uid) {
            throw new EntityNotFoundException(new TranslatableErrorMessage(
				'No card UID specified for transaction.'
			));
        }

        /** @var Card $card */
        $card = $this->getCard($entity->card_uid);
        $cardTransactionId = $entity->card_sync_id;

        $transaction = $card->getTransactionFromCounter($cardTransactionId, true);
        $transaction->has_synced = true;

        try {
            $transaction->mergeFromTransaction($entity);

            // Is this transaction new and is the card transaction count lower than this transaction?
            if (!$transaction->exists && $card->transaction_count < $transaction->card_sync_id) {
                // This transaction isn't included in the last state we know about.
                // So when correcting the balance (using the offset transaction), we should
                // not take this value into account. That's why we now change the balance we've loaded
                // when starting the merge.
                $card->original_balance += $transaction->value;
            }

            $transaction->save();
            return $transaction;

        } catch (TransactionMergeException $e) {
            // This is critical.
            \Log::error($e->getMessage());
        }
        return null;
    }

    /**
     * @param $uid
     * @return mixed
     * @throws EntityNotFoundException
     */
    private function getCard($uid)
    {
        $key = $this->getKey($uid);
        if (!isset($this->cards[$key])) {

            $card = Card::getFromUid($this->organisation, $uid, true);
            if (!$card) {
                throw new EntityNotFoundException('Card not found: ' . $uid);
            }

            $card->original_balance = $card->transactions()->lockForUpdate()->sum('value');
            $this->cards[$key] = $card;
        }

        return $this->cards[$key];
    }

    /**
     * @param $uid
     * @return string
     */
    private function getKey($uid)
    {
        return mb_strtolower($uid);
    }
}
