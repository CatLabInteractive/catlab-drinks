<?php

namespace App\Models;

use App\Models\Event;
use App\Models\MenuItem;
use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name'
    ];

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}