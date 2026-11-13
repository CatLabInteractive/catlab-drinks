import { describe, expect, it } from 'vitest';
import {
	ONLINE_ORDERING, deriveOnlineOrdering, parseVoucherValue,
	answersFromEvent, validateAnswers, eventFromAnswers,
} from './eventSettings';

describe('deriveOnlineOrdering', () => {
	it('is unpaid when unpaid online orders are allowed', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: true, is_selling: false, payment_cards: false })).toBe('unpaid');
	});
	it('is integration when selling with cards and no unpaid orders', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: false, is_selling: true, payment_cards: true })).toBe('integration');
	});
	it('is none when selling without cards', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: false, is_selling: true, payment_cards: false })).toBe('none');
	});
	it('is none when closed', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: false, is_selling: false, payment_cards: true })).toBe('none');
	});
	it('accepts integer flags', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: 0, is_selling: 1, payment_cards: 1 })).toBe('integration');
	});
});

describe('parseVoucherValue', () => {
	it('accepts a decimal comma', () => { expect(parseVoucherValue('2,50')).toBe(2.5); });
	it('accepts numbers and numeric strings', () => {
		expect(parseVoucherValue(3)).toBe(3);
		expect(parseVoucherValue('1.25')).toBe(1.25);
	});
	it('returns null for empty or garbage', () => {
		expect(parseVoucherValue('')).toBeNull();
		expect(parseVoucherValue(null)).toBeNull();
		expect(parseVoucherValue('abc')).toBeNull();
	});
});

describe('answersFromEvent', () => {
	it('gives create defaults for an event without id', () => {
		expect(answersFromEvent({})).toEqual({
			name: '', payment_cards: true, payment_cash: false, payment_vouchers: false,
			payment_voucher_value: null, online_ordering: null,
			split_orders_by_categories: false, checkin_url: '',
		});
	});
	it('derives answers from an existing event', () => {
		const answers = answersFromEvent({
			id: 7, name: 'Quiz', payment_cards: 1, payment_cash: 0, payment_vouchers: 1, payment_voucher_value: 2.5,
			allow_unpaid_online_orders: 0, is_selling: 1, split_orders_by_categories: 0, checkin_url: null,
		});
		expect(answers).toEqual({
			name: 'Quiz', payment_cards: true, payment_cash: false, payment_vouchers: true,
			payment_voucher_value: 2.5, online_ordering: 'integration',
			split_orders_by_categories: false, checkin_url: '',
		});
	});
});

describe('validateAnswers', () => {
	const valid = {
		name: 'Quiz', payment_cards: true, payment_cash: false, payment_vouchers: false,
		payment_voucher_value: null, online_ordering: 'none', split_orders_by_categories: false, checkin_url: '',
	};
	it('passes a complete answer set', () => {
		expect(validateAnswers(valid)).toEqual({ name: null, payment: null, onlineOrdering: null, valid: true });
	});
	it('requires a non-blank name', () => {
		expect(validateAnswers({ ...valid, name: '   ' }).name).toBe('required');
	});
	it('requires at least one payment method', () => {
		expect(validateAnswers({ ...valid, payment_cards: false }).payment).toBe('none_selected');
	});
	it('requires a positive voucher value when vouchers are selected', () => {
		expect(validateAnswers({ ...valid, payment_vouchers: true, payment_voucher_value: '' }).payment).toBe('voucher_value');
		expect(validateAnswers({ ...valid, payment_vouchers: true, payment_voucher_value: '0' }).payment).toBe('voucher_value');
		expect(validateAnswers({ ...valid, payment_vouchers: true, payment_voucher_value: '2,50' }).payment).toBeNull();
	});
	it('requires an online ordering answer', () => {
		expect(validateAnswers({ ...valid, online_ordering: null }).onlineOrdering).toBe('required');
		expect(validateAnswers({ ...valid, online_ordering: null }).valid).toBe(false);
	});
	it('treats integration without cards as unanswered', () => {
		expect(validateAnswers({ ...valid, payment_cards: false, payment_cash: true, online_ordering: 'integration' }).onlineOrdering).toBe('required');
	});
});

describe('eventFromAnswers', () => {
	const answers = {
		name: ' Quiz 2026 ', payment_cards: true, payment_cash: false, payment_vouchers: true,
		payment_voucher_value: '2,50', online_ordering: 'unpaid', split_orders_by_categories: true, checkin_url: ' ',
	};
	it('maps a new event and opens remote orders for an online answer', () => {
		expect(eventFromAnswers(answers, {})).toEqual({
			name: 'Quiz 2026', payment_cards: true, payment_cash: false, payment_vouchers: true,
			payment_voucher_value: 2.5, allow_unpaid_online_orders: true, is_selling: true,
			split_orders_by_categories: true, checkin_url: null,
		});
	});
	it('closes remote orders for a new event with no online ordering', () => {
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, {}).is_selling).toBe(false);
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, {}).allow_unpaid_online_orders).toBe(false);
	});
	it('maps integration to closed unpaid orders and open selling', () => {
		const result = eventFromAnswers({ ...answers, online_ordering: 'integration' }, {});
		expect(result.allow_unpaid_online_orders).toBe(false);
		expect(result.is_selling).toBe(true);
	});
	it('nulls the voucher value when vouchers are off', () => {
		expect(eventFromAnswers({ ...answers, payment_vouchers: false }, {}).payment_voucher_value).toBeNull();
	});
	it('keeps the id and other fields of an existing event', () => {
		const original = { id: 7, order_token: 'abc', is_selling: false, allow_unpaid_online_orders: true, payment_cards: true };
		const result = eventFromAnswers(answers, original);
		expect(result.id).toBe(7);
		expect(result.order_token).toBe('abc');
	});
	it('leaves is_selling alone when the online ordering answer did not change', () => {
		const paused = { id: 7, is_selling: false, allow_unpaid_online_orders: true, payment_cards: true };
		expect(eventFromAnswers(answers, paused).is_selling).toBe(false);
		const pausedIntegration = { id: 8, is_selling: false, allow_unpaid_online_orders: false, payment_cards: true };
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, pausedIntegration).is_selling).toBe(false);
	});
	it('writes is_selling when the online ordering answer changed', () => {
		const closed = { id: 7, is_selling: false, allow_unpaid_online_orders: false, payment_cards: true };
		expect(eventFromAnswers(answers, closed).is_selling).toBe(true);
		const open = { id: 9, is_selling: true, allow_unpaid_online_orders: true, payment_cards: true };
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, open).is_selling).toBe(false);
	});
	it('exposes the online ordering constants', () => {
		expect(ONLINE_ORDERING).toEqual({ NONE: 'none', UNPAID: 'unpaid', INTEGRATION: 'integration' });
	});
});
