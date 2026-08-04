<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Patron;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Atomically settle all unpaid orders of a patron.
 * Used by the settle endpoint so a tab can never end up half-paid
 * after the money was already collected.
 */
class PatronSettlementService
{
    /**
     * @param Patron $patron
     * @param string|null $paymentType e.g. 'cash', 'vouchers', 'nfc'
     * @param int $discount Discount percentage (0-100) applied by the
     *   payment (e.g. NFC card discount); recorded on the orders and
     *   applied to the item prices, mirroring the single-order NFC flow.
     * @return Collection The settled orders (empty when nothing was unpaid).
     */
    public function settle(Patron $patron, ?string $paymentType = null, int $discount = 0): Collection
    {
        $discount = max(0, min(100, $discount));

        return DB::transaction(function () use ($patron, $paymentType, $discount) {
            $orders = $patron->orders()
                ->where('payment_status', Order::PAYMENT_STATUS_UNPAID)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                /** @var Order $order */
                // Only apply the discount to orders that don't already carry
                // one: orders that were settled once, reopened and re-settled
                // keep their existing discounted prices — re-applying would
                // compound the discount.
                if ($discount > 0 && !$order->discount_percentage) {
                    $order->discount_percentage = $discount;
                    foreach ($order->order as $orderItem) {
                        $orderItem->price *= $order->getDiscountFactor();
                        $orderItem->save();
                    }
                }

                $order->payment_status = Order::PAYMENT_STATUS_PAID;
                if ($paymentType) {
                    $order->payment_type = $paymentType;
                }
                $order->save();
            }

            return $orders;
        });
    }
}
