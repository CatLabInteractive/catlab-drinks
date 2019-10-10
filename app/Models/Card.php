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

namespace App\Models;

use App\Exceptions\InsufficientFundsException;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Card
 * @package App\Models
 */
class Card extends Model
{
    public static function boot()
    {
        parent::boot();

        self::creating(function(Card $card){
            $card->order_token = str_random(16);
        });
    }

    /**
     * @param Organisation $organisation
     * @param $cardUid
     * @return Card
     */
    public static function getFromUid(Organisation $organisation, $cardUid)
    {
        // Look fo card
        $card = $organisation->cards()->where('uid', '=', $cardUid)->first();
        if (!$card) {
            $card = new Card();
            $card->uid = $cardUid;
            $card->transaction_count = 0;
            $card->organisation()->associate($organisation);

            $card->save();
        }

        return $card;
    }

    /**
     * @param $token
     * @return Card
     */
    public static function getFromOrderTokenOrAlias(Organisation $organisation, $token)
    {
        $card = Card::where('order_token', '=', $token)->first();
        if ($card) {
            return $card;
        }

        // look for aliases
        $alias = $organisation->orderTokenAliases()->where('alias', '=', $token)->first();
        if ($alias) {
            return $alias->card;
        }
        return null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingTransactions()
    {
        return $this->transactions()->where('has_synced', '=', 0)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderTokenAliases()
    {
        return $this->hasMany(CardOrderTokenAlias::class);
    }

    /**
     * @param $cardTransactionId
     * @return Transaction
     */
    public function getTransactionFromCounter($cardTransactionId)
    {
        $transaction = $this->transactions()->where('card_sync_id', '=', $cardTransactionId)->first();
        if (!$transaction) {
            $transaction = new Transaction();
            $transaction->card()->associate($this);

            $transaction->transaction_type = Transaction::TYPE_UNKNOWN;
            $transaction->card_sync_id = $cardTransactionId;
        }
        return $transaction;
    }

    /**
     * Overflow transaction is where we store transactions that we don't know anything about.
     */
    public function getOverflowTransaction()
    {
        $transaction = $this->getTransactionFromCounter(Transaction::ID_OVERFLOW);
        $transaction->transaction_type = Transaction::TYPE_OVERFLOW;
        $transaction->client_date = new \DateTime();
        $transaction->has_synced = true;

        return $transaction;
    }

    /**
     * @return int
     */
    public function getBalance()
    {
        return intval($this->transactions()->sum('value'));
    }

    /**
     * @param CardData $cardData
     */
    public function mergeFromCardData(CardData $cardData)
    {
        // do magic.
        $this->transaction_count = $cardData->transactionCount;

        $this->save();

        // check if we already know about the last 5 transactions
        $lastTransactions = $cardData->previousTransactions;

        for ($i = 0; $i < count($lastTransactions); $i ++) {

            $transactionId = $this->transaction_count - $i;
            if ($transactionId < 1) {
                break;
            }

            // check if we have this
            $transaction = $this->getTransactionFromCounter($transactionId);
            $transaction->has_synced = true;
            $transaction->value = $lastTransactions[$i];

            $transaction->save();
        }

        // now check if the balance is correct
        $transactionBalance = $this->getBalance();
        if ($transactionBalance !== $cardData->balance) {
            $overflowTransaction = $this->getOverflowTransaction();
            $overflowTransaction->value -= $transactionBalance - $cardData->balance;
            $overflowTransaction->save();
        }
    }

    /**
     * @param Order $order
     * @throws InsufficientFundsException
     */
    public function spend(Order $order)
    {
        $totalPrice = $order->getCardCost();

        $balance = $this->getBalance();
        if ($balance < $totalPrice) {
            throw new InsufficientFundsException('Insufficient funds.');
        }

        $transaction = new Transaction();
        $transaction->card()->associate($this);
        $transaction->transaction_type = Transaction::TYPE_SALE;
        $transaction->has_synced = false;
        $transaction->value = 0 - $totalPrice;
        $transaction->order_uid = $order->uid;

        $transaction->save();
    }

    /**
     * @param Order $order
     */
    public function refund(Order $order)
    {
        // first check if we actually have to do a refund
        $currentSum = $order->cardTransactions()->sum('value');
        if (abs($currentSum) > 0) {

            $transaction = new Transaction();
            $transaction->card()->associate($this);
            $transaction->transaction_type = Transaction::TYPE_REFUND;
            $transaction->has_synced = false;
            $transaction->value = 0 - $currentSum;
            $transaction->order_uid = $order->uid;

            $transaction->save();

        }
    }

    /**
     * @param Topup $topup
     */
    public function topup(Topup $topup)
    {
        $amount = $topup->getCardCredits();

        $transaction = new Transaction();
        $transaction->card()->associate($this);
        $transaction->transaction_type = Transaction::TYPE_TOPUP;
        $transaction->has_synced = false;
        $transaction->value = $amount;
        $transaction->topup_uid = $topup->uid;

        $transaction->save();
    }

    /**
     * @param Topup $topup
     */
    public function cancelTopup(Topup $topup)
    {
        // first check if we actually have to do a refund
        $currentSum = $topup->cardTransactions()->sum('value');
        if (abs($currentSum) > 0) {

            $transaction = new Transaction();
            $transaction->card()->associate($this);
            $transaction->transaction_type = Transaction::TYPE_REFUND;
            $transaction->has_synced = false;
            $transaction->value = 0 - $currentSum;
            $transaction->topup_uid = $topup->uid;

            $transaction->save();

        }
    }

    /**
     * @param array $items
     */
    public function setOrderTokenAliases(array $items)
    {
        $touches = [];
        foreach ($items as $alias) {
            // if not exists yet, create.
            $t = $this->orderTokenAliases->where('alias', '=', $alias)->first();
            if (!$t) {

                // Was it assigned somewhere else? delete all the things!
                CardOrderTokenAlias::where('organisation_id', '=', $this->organisation->id)
                    ->where('alias', '=', $alias)
                    ->delete();

                $model = new CardOrderTokenAlias();
                $model->alias = $alias;

                $model->organisation()->associate($this->organisation);
                $model->card()->associate($this);

                $model->save();
                $touches[] = $model->id;
            } else {
                $touches[] = $t->id;
            }
        }

        $this->orderTokenAliases()->whereNotIn('id', $touches)->delete();
    }

    /**
     * @return string[]
     */
    public function getOrderTokenAliases()
    {
        return $this->orderTokenAliases()->get()->map(
            function(CardOrderTokenAlias $v) {
                return $v->alias;
            }
        );
    }
}
