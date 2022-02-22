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
use App\Exceptions\TransactionCountException;
use DB;
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
     * @param bool $lockForUpdate
     * @return Card
     */
    public static function getFromUid(Organisation $organisation, $cardUid, $lockForUpdate = false)
    {
        // Look fo card
        $card = $organisation->cards()->where('uid', '=', $cardUid);
        if ($lockForUpdate) {
            $card->lockForUpdate();
        }
        $card = $card->first();

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
     * @param Organisation $organisation
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
        $alias = $organisation->orderTokenAliases()
            ->notExpired()
            ->where('alias', '=', $token)->first();
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
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function orders()
    {
        return Order::query()
            ->leftJoin('card_transactions', 'card_transactions.order_id', '=', 'orders.id')
            ->where('card_transactions.card_id', '=', $this->id);
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
        return $this->hasMany(CardOrderTokenAlias::class)
            ->notExpired();
    }

    /**
     * @param $cardTransactionId
     * @param bool $lockForUpdate
     * @return Transaction
     */
    public function getTransactionFromCounter($cardTransactionId, $lockForUpdate = false)
    {
        $transaction = $this->transactions()->where('card_sync_id', '=', $cardTransactionId);
        if ($lockForUpdate) {
            $transaction->lockForUpdate();
        }

        $transaction = $transaction->first();

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
     * @param bool $forUpdate
     * @return Transaction
     * @throws \Exception
     */
    public function getOverflowTransaction($forUpdate = false)
    {
        $transaction = $this->getTransactionFromCounter(Transaction::ID_OVERFLOW, $forUpdate);
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
     * @param Order $order
     * @throws InsufficientFundsException
     */
    public function spend(Order $order)
    {
        $order->discount_percentage = $this->discount_percentage;
        $totalPrice = ceil($order->getCurrentCardCost() * $order->getDiscountFactor());

        $balance = $this->getBalance();
        if ($totalPrice > 0 && $balance < $totalPrice) {
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
     * @throws \Throwable
     */
    public function refund(Order $order)
    {
        // first check if we actually have to do a refund
        DB::transaction(function() use ($order) {

            $currentSum = $order->cardTransactions()->lockForUpdate()->sum('value');
            if (abs($currentSum) > 0) {

                $transaction = new Transaction();
                $transaction->card()->associate($this);
                $transaction->transaction_type = Transaction::TYPE_REFUND;
                $transaction->has_synced = false;
                $transaction->value = 0 - $currentSum;
                $transaction->order_uid = $order->uid;

                $transaction->save();

            }

        }, 5);
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
     * @throws \Throwable
     */
    public function cancelTopup(Topup $topup)
    {
        DB::transaction(function() use ($topup) {

            // first check if we actually have to do a refund
            $currentSum = $topup->cardTransactions()->lockForUpdate()->sum('value');
            if (abs($currentSum) > 0) {

                $transaction = new Transaction();
                $transaction->card()->associate($this);
                $transaction->transaction_type = Transaction::TYPE_REFUND;
                $transaction->has_synced = false;
                $transaction->value = 0 - $currentSum;
                $transaction->topup_uid = $topup->uid;

                $transaction->save();

            }

        }, 5);
    }

    /**
     * @param array $items
     */
    public function setOrderTokenAliases(array $items)
    {
        $touches = [];
        foreach ($items as $alias) {
            // if not exists yet, create.

            /** @var CardOrderTokenAlias $t */
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
                $model->touchExpirationDate();

                $model->save();
                $touches[] = $model->id;
            } else {
                /*
                 * don't refresh existing tokens (for now)
                $t->touchExpirationDate();
                $t->save();
                */

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

    /**
     * @return string|null
     */
    public function getName()
    {
        if (!$this->exists) {
            return null;
        }

        if (!$this->name) {
            $namedOrder = $this->orders()
                ->whereNotNull('orders.requester')
                ->orderBy('orders.id', 'desc')
                ->first();

            // Try to guess the client name.
            if ($namedOrder) {
                $this->name = $namedOrder->requester;
                $this->save();
            }
        }
        return $this->name;
    }
}
