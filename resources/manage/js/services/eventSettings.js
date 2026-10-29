/**
 * Maps the three plain questions of the event form (name, how guests pay,
 * can guests order from their own phone) onto the event flags the API
 * stores, and back. Pure functions, no Vue.
 */

export const ONLINE_ORDERING = {
	NONE: 'none',
	UNPAID: 'unpaid',
	INTEGRATION: 'integration',
};

/**
 * Which online-ordering answer a stored event corresponds to.
 * "No" and "integration" both store allow_unpaid_online_orders = false,
 * so the distinction is derived from whether remote orders are open
 * and cards are accepted.
 */
export function deriveOnlineOrdering(event) {
	if (event.allow_unpaid_online_orders) {
		return ONLINE_ORDERING.UNPAID;
	}
	if (event.is_selling && event.payment_cards) {
		return ONLINE_ORDERING.INTEGRATION;
	}
	return ONLINE_ORDERING.NONE;
}

/**
 * Parses a voucher value typed by a human; accepts a decimal comma.
 */
export function parseVoucherValue(value) {
	if (value === null || value === undefined) {
		return null;
	}
	const normalized = String(value).trim().replace(',', '.');
	if (normalized === '') {
		return null;
	}
	const parsed = parseFloat(normalized);
	return Number.isFinite(parsed) ? parsed : null;
}

/**
 * The answers the form edits, derived from an event. An event without id
 * yields the defaults for a new event.
 */
export function answersFromEvent(event) {
	const source = event || {};
	if (!source.id) {
		return {
			name: '',
			payment_cards: true,
			payment_cash: false,
			payment_vouchers: false,
			payment_voucher_value: null,
			online_ordering: null,
			split_orders_by_categories: false,
			checkin_url: '',
		};
	}

	return {
		name: source.name || '',
		payment_cards: !!source.payment_cards,
		payment_cash: !!source.payment_cash,
		payment_vouchers: !!source.payment_vouchers,
		payment_voucher_value: source.payment_voucher_value === undefined ? null : source.payment_voucher_value,
		online_ordering: deriveOnlineOrdering(source),
		split_orders_by_categories: !!source.split_orders_by_categories,
		checkin_url: source.checkin_url || '',
	};
}

export function validateAnswers(answers) {
	const name = String(answers.name || '').trim() === '' ? 'required' : null;

	let payment = null;
	if (!answers.payment_cards && !answers.payment_cash && !answers.payment_vouchers) {
		payment = 'none_selected';
	} else if (answers.payment_vouchers) {
		const value = parseVoucherValue(answers.payment_voucher_value);
		if (value === null || value <= 0) {
			payment = 'voucher_value';
		}
	}

	let onlineOrdering = null;
	if (
		!answers.online_ordering
		|| (answers.online_ordering === ONLINE_ORDERING.INTEGRATION && !answers.payment_cards)
	) {
		onlineOrdering = 'required';
	}

	return {
		name,
		payment,
		onlineOrdering,
		valid: name === null && payment === null && onlineOrdering === null,
	};
}

/**
 * The payload to send to the API: the original event (so id and other
 * fields pass through) with the answers applied.
 *
 * is_selling is written for a new event, and for an existing event only
 * when the online-ordering answer changed; editing the name of a paused
 * event must not reopen remote orders.
 */
export function eventFromAnswers(answers, original) {
	const base = original || {};
	const onlineOrdering = answers.online_ordering;
	const checkinUrl = String(answers.checkin_url || '').trim();

	let isSelling;
	if (!base.id || onlineOrdering !== deriveOnlineOrdering(base)) {
		isSelling = onlineOrdering !== ONLINE_ORDERING.NONE;
	} else {
		isSelling = base.is_selling;
	}

	return Object.assign({}, base, {
		name: String(answers.name || '').trim(),
		payment_cards: !!answers.payment_cards,
		payment_cash: !!answers.payment_cash,
		payment_vouchers: !!answers.payment_vouchers,
		payment_voucher_value: answers.payment_vouchers ? parseVoucherValue(answers.payment_voucher_value) : null,
		allow_unpaid_online_orders: onlineOrdering === ONLINE_ORDERING.UNPAID,
		is_selling: isSelling,
		split_orders_by_categories: !!answers.split_orders_by_categories,
		checkin_url: checkinUrl === '' ? null : checkinUrl,
	});
}
