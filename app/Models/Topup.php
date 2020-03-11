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
 * Class Topup
 * @package App\Models
 */
class Topup extends Model
{
    /**
     *
     */
    public static function boot()
    {
        parent::boot();

        self::created(function(Topup $topup){

            // look for any transactions that could be linked
            $transactions = Transaction::where('topup_uid', '=', $topup->uid)->get();
            foreach ($transactions as $transaction) {
                /** @var Transaction $transaction */
                $transaction->topup()->associate($topup);
                $transaction->save();
            }

        });
    }

    const TYPE_ONLINE = 'online';
    const TYPE_MANUAL = 'manual';

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'type',
        'amount',
        'status'
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
    public function logs()
    {
        return $this->hasMany(TopupLog::class);
    }

    /**
     * @param $data
     */
    public function success($data)
    {
        if ($this->isSuccess()) {
            // already processed.
            return;
        }

        $this->status = self::STATUS_SUCCESS;
        $this->save();

        $this->card->topup($this);
    }

    /**
     * @param $data
     */
    public function cancel($data)
    {
        if ($this->isCancelled()) {
            // already processed
            return;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();

        // is this a topup that is already processed
        if ($this->isSuccess()) {
            $this->card->cancelTopup($this);
        }
    }

    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * @return bool
     */
    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
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
    public function getCardCredits()
    {
        return ceil($this->amount * 100);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
