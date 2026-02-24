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

use App\Exceptions\TransactionCountException;
use App\Models\Card;
use App\Models\CardData;
use App\Models\Device;
use App\Models\Transaction;
use DB;

/**
 * Class CardDataMerger
 * @package App\Tools
 */
class CardDataMerger
{
    /**
     * @var Card
     */
    private $card;

    /**
     * @var Device|null
     */
    private $signingDevice;

    /**
     * CardDataMerger constructor.
     * @param Card $card
     * @param Device|null $signingDevice
     */
    public function __construct(Card $card, Device $signingDevice = null)
    {
        $this->card = $card;
        $this->signingDevice = $signingDevice;
    }

    /**
     * @param CardData $cardData
     * @throws \Throwable
     */
    public function merge(CardData $cardData)
    {
        DB::transaction(function() use ($cardData) {

            // we need to refresh the card data to eliminate race conditions.
            // locking the card for update will essentially make sure that no other process
            // can do any changes to the card or its transactions while we do our calculations here.

            /** @var Card $card */
            $card = Card::where('id', '=', $this->card->id)->lockForUpdate()->first();

            if ($card->transaction_count > $cardData->transactionCount) {
                throw new TransactionCountException('Transaction count is lower than our own transaction count');
            }

            // do magic.
            $card->transaction_count = $cardData->transactionCount;
            $card->discount_percentage = $cardData->discount_percentage;

            // Track which device last signed this card
            if ($this->signingDevice) {
                $card->last_signing_device_id = $this->signingDevice->id;
            }

            $card->save();

            // check if we already know about the last 5 transactions
            $lastTransactions = $cardData->previousTransactions;

            for ($i = 0; $i < count($lastTransactions); $i ++) {

                $transactionId = $card->transaction_count - $i;
                if ($transactionId < 1) {
                    break;
                }

                // make a temporary transaction that we can use to merge with the existing transactions
                $incompleteTransaction = new Transaction();
                $incompleteTransaction->card_sync_id = $transactionId;
                $incompleteTransaction->has_synced = true;
                $incompleteTransaction->value = $lastTransactions[$i];

                // check if we have this
                $transaction = $card->getTransactionFromCounter($incompleteTransaction->card_sync_id, true);
                $transaction->has_synced = true;

                // merge the transaction (in case it doesn't exist yet)
                $transaction->mergeFromTransaction($incompleteTransaction);

                $transaction->save();
            }

            // now check if the balance is correct
            $transactionBalance = $card->getBalance();
            if (abs($transactionBalance - $cardData->balance) > 0) {
                $overflowTransaction = $card->getOverflowTransaction();
                $overflowTransaction->value -= $transactionBalance - $cardData->balance;
                $overflowTransaction->save();
            }

        }, 5);

        // finally, refresh our own internal card from database
        $this->card->refresh();
    }
}
