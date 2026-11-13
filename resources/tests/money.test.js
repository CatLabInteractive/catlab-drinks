/**
 * The shared money formatter labels amounts with the organisation's currency
 * in the user's interface language, always with two decimals.
 */
import { describe, it, expect, afterEach } from 'vitest';
import {
	DEFAULT_CURRENCY,
	FALLBACK_CURRENCIES,
	isCurrencyCode,
	setCurrency,
	getCurrency,
	formatMoney,
	currencySymbol,
	listCurrencies,
	currencyOptions,
} from '../shared/js/utils/money';

// Intl puts a non-breaking space (U+00A0) between symbol and number for some locales.
const n = (s) => s.replace(/ /g, ' ');

describe('money', () => {
	afterEach(() => setCurrency('EUR'));

	it('defaults to the euro', () => {
		expect(DEFAULT_CURRENCY).toBe('EUR');
		expect(getCurrency()).toBe('EUR');
	});

	it('formats euro the Dutch way and the English way', () => {
		expect(n(formatMoney(2.5, 'EUR', 'nl'))).toBe('€ 2,50');
		expect(formatMoney(2.5, 'EUR', 'en')).toBe('€2.50');
	});

	it('formats dollars', () => {
		expect(formatMoney(2.5, 'USD', 'en')).toBe('$2.50');
	});

	it('keeps two decimals for zero-decimal currencies', () => {
		expect(n(formatMoney(500, 'JPY', 'en'))).toBe('¥500.00');
		expect(n(formatMoney(150.5, 'JPY', 'en'))).toBe('¥150.50');
	});

	it('accepts numeric strings and rejects non-numbers', () => {
		expect(formatMoney('2.5', 'USD', 'en')).toBe('$2.50');
		expect(formatMoney(null)).toBe('');
		expect(formatMoney(undefined)).toBe('');
		expect(formatMoney(NaN)).toBe('');
	});

	it('falls back to CODE amount for a malformed code', () => {
		expect(formatMoney(2.5, 'EU', 'en')).toBe('EU 2.50');
	});

	it('uses the module currency by default', () => {
		setCurrency('USD');
		expect(formatMoney(1, undefined, 'en')).toBe('$1.00');
	});

	it('recognises well-formed codes only', () => {
		expect(isCurrencyCode('EUR')).toBe(true);
		expect(isCurrencyCode('eur')).toBe(false);
		expect(isCurrencyCode('EURO')).toBe(false);
		expect(isCurrencyCode(null)).toBe(false);
		expect(isCurrencyCode(123)).toBe(false);
	});

	it('ignores invalid codes in setCurrency', () => {
		expect(setCurrency('usd')).toBe('EUR');
		expect(setCurrency(undefined)).toBe('EUR');
		expect(setCurrency(null)).toBe('EUR');
		expect(setCurrency('')).toBe('EUR');
		expect(setCurrency('GBP')).toBe('GBP');
		expect(getCurrency()).toBe('GBP');
	});

	it('returns the symbol alone', () => {
		expect(currencySymbol('EUR', 'en')).toBe('€');
		expect(currencySymbol('USD', 'nl')).toBe('US$');
		expect(currencySymbol('XYZ', 'en')).toBe('XYZ');
	});

	it('lists currencies by name', () => {
		const list = listCurrencies('en');
		expect(list).toContainEqual({ code: 'EUR', name: 'Euro' });
		expect(list).toContainEqual({ code: 'USD', name: 'US Dollar' });
		const names = list.map((c) => c.name);
		expect(names).toEqual([...names].sort((a, b) => a.localeCompare(b, 'en')));
	});

	it('falls back to a short list without Intl.supportedValuesOf and keeps the current code', () => {
		const original = Intl.supportedValuesOf;
		Intl.supportedValuesOf = undefined;
		try {
			expect(listCurrencies('en').map((c) => c.code)).toEqual(expect.arrayContaining(FALLBACK_CURRENCIES));
			expect(listCurrencies('en')).toHaveLength(FALLBACK_CURRENCIES.length);
			const options = currencyOptions('HUF', 'en');
			expect(options).toContainEqual({ value: 'HUF', text: 'Hungarian Forint (HUF)' });
			expect(options).toContainEqual({ value: 'EUR', text: 'Euro (EUR)' });
		} finally {
			Intl.supportedValuesOf = original;
		}
	});
});
