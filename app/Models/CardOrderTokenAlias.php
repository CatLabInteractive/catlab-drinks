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
 * Class CardOrderTokenAlias
 * @package App\Models
 */
class CardOrderTokenAlias extends Model
{
    protected $fillable = [
        'alias'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'expires_at'
    ];

    /**
     * @var string
     */
    protected $table = 'card_order_token_aliases';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return $this
     */
    public function touchExpirationDate()
    {
        $this->expires_at = (new \DateTime())->add(new \DateInterval('P1D'));
        return $this;
    }

    /**
     * @param $builder
     * @return void
     */
    public function scopeNotExpired($builder)
    {
        $builder->where('expires_at', '>', new \DateTime());
    }
}
