<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

/**
 * Class Patron
 * @package App\Models
 */
class Patron extends Model
{
    use HasFactory;

    protected $table = 'patrons';

    protected $fillable = [
        'name',
    ];

    /**
     * Cross-event table assignment is never valid; enforced at model level
     * so it holds on every write path.
     */
    protected static function booted()
    {
        self::saving(function (Patron $patron) {
            if ($patron->isDirty('table_id') && $patron->table_id !== null) {
                $table = Table::find($patron->table_id);
                if (!$table || (int) $table->event_id !== (int) $patron->event_id) {
                    throw ValidationException::withMessages([
                        'table_id' => 'Table does not belong to this event.'
                    ]);
                }
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
    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the total outstanding (unpaid) balance.
     * @return float
     */
    public function getOutstandingBalance()
    {
        $total = 0;
        foreach ($this->orders()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)->get() as $order) {
            $total += $order->getPrice();
        }
        return $total;
    }

    /**
     * Check if this patron has any unpaid orders.
     * @return bool
     */
    public function hasUnpaidOrders()
    {
        return $this->orders()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)->exists();
    }
}
