<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Table
 * @package App\Models
 */
class Table extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tables';

    protected $fillable = [
        'table_number',
        'name',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function patrons()
    {
        return $this->hasMany(Patron::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the latest patron assigned to this table.
     * @return Patron|null
     */
    public function getLatestPatron()
    {
        return $this->patrons()->latest()->first();
    }

    /**
     * Bulk-generate tables for an event.
     * Queries the highest current active (non-soft-deleted) table_number and increments.
     *
     * @param Event $event
     * @param int $count
     * @return Table[]
     */
    public static function bulkGenerate(Event $event, int $count): array
    {
        $highestNumber = $event->tables()
            ->withoutTrashed()
            ->max('table_number') ?? 0;

        $tables = [];
        for ($i = 1; $i <= $count; $i++) {
            $number = $highestNumber + $i;

            $table = new self();
            $table->table_number = $number;
            $table->name = 'Table ' . $number;
            $table->event()->associate($event);
            $table->save();

            $tables[] = $table;
        }

        return $tables;
    }
}
