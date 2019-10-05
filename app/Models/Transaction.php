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

use App\Exceptions\TransactionMergeException;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Transaction
 * @package App\Models
 */
class Transaction extends Model
{
    /**
     *
     */
    public static function boot()
    {
        parent::boot();

        self::saving(function(Transaction $transaction){
            // Look for any orders that might have registered already
            if ($transaction->order_uid) {
                $order = Order::where('uid', '=', $transaction->order_uid)->first();
                if ($order) {
                    $transaction->order()->associate($order);
                }
            }

            if ($transaction->topup_uid) {
                $topup = Topup::where('uid', '=', $transaction->topup_uid)->first();
                if ($topup) {
                    $transaction->topup()->associate($topup);
                }
            }
        });
    }

    protected $table = 'card_transactions';

    protected $dates = [
        'created_at',
        'updated_at',
        'client_date'
    ];

    const TYPE_SALE = 'sale';
    const TYPE_TOPUP = 'topup';
    const TYPE_REFUND = 'refund';
    const TYPE_UNKNOWN = 'unknown';
    const TYPE_OVERFLOW = 'overflow';

    // the id that will be used as an overflow transaction
    const ID_OVERFLOW = -1;

    protected $fillable = [
        'transaction_type',
        'card_sync_id',
        'value'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function topup()
    {
        return $this->belongsTo(Topup::class);
    }

    /**
     * @param Transaction $entity
     */
    public function mergeFromTransaction(Transaction $entity)
    {
        // is this transaction expected?
        if ($this->transaction_type !== Transaction::TYPE_UNKNOWN) {
            // we can't merge this, check if the values are correct
            if ($this->value !== $entity->value) {
                throw new TransactionMergeException("Failed merging transaction: value does not match.");
            }

            return;
        }

        $this->transaction_type = $entity->transaction_type;
        $this->client_date = $entity->client_date;
        $this->value = $entity->value;

        // merge transaction id
        $this->order_uid = $entity->order_uid;
        $this->topup_uid = $entity->topup_uid;
    }
}
