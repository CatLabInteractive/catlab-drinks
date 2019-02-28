<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use CatLab\Charon\Laravel\Database\Model;

class MenuItem extends Model
{
    protected $table = 'menu_items';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param Builder $query
     */
    public function scopeOnSale(Builder $query)
    {
        $query->where('is_selling', 1);
    }
}
