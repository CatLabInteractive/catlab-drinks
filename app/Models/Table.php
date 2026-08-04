<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

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
     * The unique index on (event_id, table_number) covers soft-deleted rows,
     * so the check must too. Lives in a model event so it holds on every
     * write path.
     */
    protected static function booted()
    {
        self::saving(function (Table $table) {
            if (!$table->isDirty('table_number') && !$table->isDirty('event_id')) {
                return;
            }

            $collision = self::withTrashed()
                ->where('event_id', $table->event_id)
                ->where('table_number', $table->table_number)
                ->where('id', '!=', $table->id ?? 0)
                ->exists();

            if ($collision) {
                throw ValidationException::withMessages([
                    'table_number' => 'A table with this number already exists for this event.'
                ]);
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
            $tables[] = self::restoreOrCreate($event, $highestNumber + $i);
        }

        return $tables;
    }

    /**
     * Return the active table with this number, reviving a soft-deleted one
     * if that's what holds the unique (event_id, table_number) slot.
     * Safe under concurrent creation of the same number.
     *
     * @param Event $event
     * @param int $tableNumber
     * @return Table
     */
    public static function restoreOrCreate(Event $event, int $tableNumber): self
    {
        $table = $event->tables()
            ->withTrashed()
            ->where('table_number', $tableNumber)
            ->first();

        if ($table) {
            if ($table->trashed()) {
                $table->restore();
            }
            return $table;
        }

        $table = new self();
        $table->table_number = $tableNumber;
        $table->name = 'Table ' . $tableNumber;
        $table->event()->associate($event);

        try {
            $table->save();
        } catch (QueryException $e) {
            // Lost a race against a concurrent insert of the same number:
            // the row that beat us is the table we wanted.
            $existing = $event->tables()
                ->withTrashed()
                ->where('table_number', $tableNumber)
                ->first();

            if (!$existing) {
                throw $e;
            }

            if ($existing->trashed()) {
                $existing->restore();
            }
            return $existing;
        }

        return $table;
    }
}
