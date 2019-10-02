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
            $card->balance = 0;
            $card->organisation()->associate($organisation);

            $card->save();
        }

        return $card;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getPendingTransactions() {
        return $this->transactions()->whereNull('client_date')->get();
    }

    /**
     * @param $cardTransactionId
     * @return Transaction|Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
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
}
