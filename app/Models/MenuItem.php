<?php

namespace App\Models;

use App\Models\Concerns\AssignsNextPosition;
use Illuminate\Database\Eloquent\Builder;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes, AssignsNextPosition;

    protected $table = 'menu_items';

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function heading()
    {
        return $this->belongsTo(MenuHeading::class, 'heading_id');
    }

    /**
     * @param Builder $query
     */
    public function scopeOnSale(Builder $query)
    {
        $query->where('is_selling', 1);
    }
}
