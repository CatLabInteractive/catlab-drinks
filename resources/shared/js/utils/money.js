/*
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**
 * Money formatting for the three frontends.
 *
 * Money in CatLab Drinks has no currency of its own: prices are plain decimals
 * and card balances are plain cents. The organisation's currency is a label,
 * held here as module state and applied by formatMoney(). Amounts always show
 * two decimals, whatever the currency, so the label equals the stored number.
 * The locale is the user's interface language, not the organisation's.
 */
import { getLocale } from '../i18n/index';

export const DEFAULT_CURRENCY = 'EUR';

/**
 * Picker contents for browsers without Intl.supportedValuesOf (older Safari).
 */
export const FALLBACK_CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'CAD', 'AUD'];

let currentCurrency = DEFAULT_CURRENCY;

/**
 * An ISO 4217 alphabetic code: exactly three uppercase ASCII letters.
 * @param {*} value
 * @returns {boolean}
 */
export function isCurrencyCode(value) {
	return typeof value === 'string' && /^[A-Z]{3}$/.test(value);
}

/**
 * Store the organisation's currency. Anything that is not a well-formed code
 * (an old cached payload without the field, a null) leaves the current value.
 * @param {*} code
 * @returns {string} the currency now in use
 */
export function setCurrency(code) {
	if (isCurrencyCode(code)) {
		currentCurrency = code;
	}
	return currentCurrency;
}

/**
 * @returns {string}
 */
export function getCurrency() {
	return currentCurrency;
}

/**
 * @param {number|string|null|undefined} amount
 * @param {string} currency
 * @param {string} locale
 * @returns {string} '' for nothing to show, "CODE 2.50" when the code cannot be formatted
 */
export function formatMoney(amount, currency = getCurrency(), locale = getLocale()) {
	if (amount === null || amount === undefined) {
		return '';
	}

	const value = Number(amount);
	if (!Number.isFinite(value)) {
		return '';
	}

	try {
		return new Intl.NumberFormat(locale, {
			style: 'currency',
			currency,
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}).format(value);
	} catch (e) {
		if (e instanceof RangeError) {
			return `${currency} ${value.toFixed(2)}`;
		}
		throw e;
	}
}

/**
 * The symbol on its own, for an input prefix.
 * @param {string} currency
 * @param {string} locale
 * @returns {string} the code itself when the browser has no symbol for it
 */
export function currencySymbol(currency = getCurrency(), locale = getLocale()) {
	try {
		const part = new Intl.NumberFormat(locale, { style: 'currency', currency })
			.formatToParts(0)
			.find((p) => p.type === 'currency');
		return part ? part.value : currency;
	} catch (e) {
		if (e instanceof RangeError) {
			return currency;
		}
		throw e;
	}
}

function currencyName(code, locale) {
	try {
		const name = new Intl.DisplayNames([locale], { type: 'currency' }).of(code);
		return name || code;
	} catch (e) {
		return code;
	}
}

/**
 * @param {string} locale
 * @returns {Array<{code: string, name: string}>} sorted by name
 */
export function listCurrencies(locale = getLocale()) {
	const codes = typeof Intl.supportedValuesOf === 'function'
		? Intl.supportedValuesOf('currency')
		: FALLBACK_CURRENCIES;

	return codes
		.map((code) => ({ code, name: currencyName(code, locale) }))
		.sort((a, b) => a.name.localeCompare(b.name, locale));
}

/**
 * Options for a select, with the organisation's current code always present
 * so the picker never shows blank.
 * @param {string} current
 * @param {string} locale
 * @returns {Array<{value: string, text: string}>}
 */
export function currencyOptions(current, locale = getLocale()) {
	const list = listCurrencies(locale);
	if (isCurrencyCode(current) && !list.some((c) => c.code === current)) {
		list.push({ code: current, name: currencyName(current, locale) });
		list.sort((a, b) => a.name.localeCompare(b.name, locale));
	}
	return list.map((c) => ({ value: c.code, text: `${c.name} (${c.code})` }));
}
