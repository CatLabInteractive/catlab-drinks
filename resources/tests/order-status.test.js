import { describe, it, expect } from 'vitest';
import {
	ORDER_STATUS,
	PAYMENT_STATUS,
	statusVariant,
	paymentStatusVariant,
	isBarQueueOrder,
} from '../shared/js/orderStatus';

describe('orderStatus constants', () => {
	it('matches the backend Order model constants', () => {
		expect(ORDER_STATUS.PENDING).toBe('pending');
		expect(ORDER_STATUS.PROCESSED).toBe('processed');
		expect(ORDER_STATUS.PREPARED).toBe('prepared');
		expect(ORDER_STATUS.DELIVERED).toBe('delivered');
		expect(ORDER_STATUS.DECLINED).toBe('declined');
		expect(PAYMENT_STATUS.UNPAID).toBe('unpaid');
		expect(PAYMENT_STATUS.PAID).toBe('paid');
		expect(PAYMENT_STATUS.VOIDED).toBe('voided');
	});
});

describe('badge variants', () => {
	it('maps statuses to bootstrap variants', () => {
		expect(statusVariant('pending')).toBe('warning');
		expect(statusVariant('prepared')).toBe('info');
		expect(statusVariant('delivered')).toBe('success');
		expect(statusVariant('declined')).toBe('danger');
		expect(statusVariant('anything-else')).toBe('secondary');
	});

	it('maps payment statuses to bootstrap variants', () => {
		expect(paymentStatusVariant('unpaid')).toBe('warning');
		expect(paymentStatusVariant('paid')).toBe('success');
		expect(paymentStatusVariant('voided')).toBe('danger');
		expect(paymentStatusVariant(null)).toBe('secondary');
	});
});

describe('isBarQueueOrder', () => {
	it('always shows non-table orders', () => {
		expect(isBarQueueOrder({ patron_id: null }, { bar_prepares_table_orders: false })).toBe(true);
		expect(isBarQueueOrder({ patron_id: null }, null)).toBe(true);
	});

	it('hides table-service orders when the bar does not prepare them', () => {
		expect(isBarQueueOrder({ patron_id: 5 }, { bar_prepares_table_orders: false })).toBe(false);
		expect(isBarQueueOrder({ patron_id: 5 }, null)).toBe(false);
	});

	it('shows table-service orders when the bar prepares them', () => {
		expect(isBarQueueOrder({ patron_id: 5 }, { bar_prepares_table_orders: true })).toBe(true);
	});
});
