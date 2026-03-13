<?php

namespace App\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
