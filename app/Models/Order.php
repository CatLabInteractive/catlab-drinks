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

use CatLab\Charon\Laravel\Database\Model;

/**
 * Class Order
 * @package App\Models
 */
class Order extends Model
{
    private $cardTransactions;

    /**
     *
     */
    public static function boot()
    {
        parent::boot();

        self::created(function(Order $order){

            // look for any transactions that could be linked
            $transactions = Transaction::where('order_uid', '=', $order->uid)->get();
            foreach ($transactions as $transaction) {
                /** @var Transaction $transaction */
                $transaction->order()->associate($order);
                $transaction->save();
            }

        });

        self::updating(function(Order $order) {

            $originalStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            if (
                $originalStatus !== $newStatus &&
                $newStatus === self::STATUS_DECLINED
            ) {

                // do we have a card attached?
                $transactions = $order->cardTransactions()->get();
                if (count($transactions) === 1) {
                    // refund this transaction
                    /** @var Card $card */
                    $card = $transactions[0]->card;
                    $card->refund($order);
                }

            }
        });
    }

    const STATUS_DECLINED = 'declined';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';

    /**
     * @var string
     */
    protected $table = 'orders';

    /**
     * @var string
     */
    protected $cardToken;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function order()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     *
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param $token
     * @return Order
     */
    public function setCardToken($token)
    {
        $this->cardToken = $token;
        return $this;
    }

    public function getCardToken()
    {
        return $this->cardToken;
    }

    /**
     * @return float
     */
    public function getTotalCost()
    {
        $price = 0;
        foreach ($this->order as $order) {
            $price += $order->amount * $order->menuItem->price;
        }
        return $price;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cardTransactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Return the amount of card credits that this order is worth.
     * @return float
     */
    public function getCardCost()
    {
        return ceil($this->getTotalCost() * 100);
    }
}
