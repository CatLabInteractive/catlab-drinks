<?php

namespace App\Models;

use App\Models\Concerns\AssignsNextPosition;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A title on the order screens that groups products. Not related to routing, see Category.
 */
class MenuHeading extends Model
{
    use HasFactory, SoftDeletes, AssignsNextPosition;

    protected $table = 'menu_headings';

    protected static function booted()
    {
        static::deleting(function (MenuHeading $heading) {
            // A soft delete keeps the row, so the foreign key does not clear the products.
            MenuItem::query()->where('heading_id', $heading->id)->update(['heading_id' => null]);
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class, 'heading_id');
    }
}
