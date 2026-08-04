/*
 * Order status constants shared across the three frontend apps.
 * Values must match the constants on App\Models\Order.
 */

export const ORDER_STATUS = {
	PENDING: 'pending',
	PROCESSED: 'processed',
	PREPARED: 'prepared',
	DELIVERED: 'delivered',
	DECLINED: 'declined',
};

export const PAYMENT_STATUS = {
	UNPAID: 'unpaid',
	PAID: 'paid',
	VOIDED: 'voided',
};

export function statusVariant(status) {
	switch (status) {
		case ORDER_STATUS.PENDING: return 'warning';
		case ORDER_STATUS.PREPARED: return 'info';
		case ORDER_STATUS.DELIVERED: return 'success';
		case ORDER_STATUS.DECLINED: return 'danger';
		default: return 'secondary';
	}
}

export function paymentStatusVariant(status) {
	switch (status) {
		case PAYMENT_STATUS.UNPAID: return 'warning';
		case PAYMENT_STATUS.PAID: return 'success';
		case PAYMENT_STATUS.VOIDED: return 'danger';
		default: return 'secondary';
	}
}

/**
 * Should this order appear in the bar's remote order queue?
 * Table-service orders (patron_id set) only pass through the bar when
 * the event routes them there via bar_prepares_table_orders.
 */
export function isBarQueueOrder(order, event) {
	if (!order.patron_id) {
		return true;
	}
	return !!(event && event.bar_prepares_table_orders);
}
