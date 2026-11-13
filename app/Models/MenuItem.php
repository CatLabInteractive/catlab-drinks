<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'menu_items';

    protected static function booted()
    {
        static::creating(function (MenuItem $item) {
            if (empty($item->position)) {
                $item->position = (int) static::withTrashed()
                    ->where('event_id', $item->event_id)
                    ->max('position') + 1;
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param Builder $query
     */
    public function scopeOnSale(Builder $query)
    {
        $query->where('is_selling', 1);
    }
}
