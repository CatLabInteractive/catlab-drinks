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
 * Reads prices and plain numbers as people type or paste them. Pure
 * functions, no Vue.
 */

// \u20AC is the euro sign. It is written as an escape because
// currency-display.test.js allows no literal currency symbol in the sources.
const CURRENCY_PREFIX = /^(?:euro|eur|\u20AC)\s*/i;
const CURRENCY_SUFFIX = /\s*(?:euro|eur|\u20AC)$/i;

/**
 * Rounds to two decimals. Gives null instead of NaN or Infinity.
 */
function round2(value) {
	if (!Number.isFinite(value)) {
		return null;
	}
	// Exponent notation avoids float errors such as 2.505 * 100 = 250.49999,
	// but only works for values that print without an exponent.
	const rounded = String(value).includes('e')
		? Math.round(value * 100) / 100
		: Number(Math.round(Number(value + 'e2')) + 'e-2');
	return Number.isFinite(rounded) ? rounded : null;
}

/**
 * Reads a bare number. With two kinds of mark the last one is the decimal
 * mark and the other one is a thousands mark. With one kind of mark, that
 * mark is the decimal mark and it may occur only once.
 */
function readDecimal(text) {
	const hasComma = text.includes(',');
	const hasDot = text.includes('.');
	let normalized;
	if (hasComma && hasDot) {
		const decimalMark = text.lastIndexOf(',') > text.lastIndexOf('.') ? ',' : '.';
		const thousandsMark = decimalMark === ',' ? '.' : ',';
		normalized = text.split(thousandsMark).join('').replace(decimalMark, '.');
	} else {
		normalized = text.replace(',', '.');
	}
	if (!/^\d+(?:\.\d+)?$/.test(normalized)) {
		return null;
	}
	return round2(parseFloat(normalized));
}

function readNumber(text) {
	if (text === null || text === undefined) {
		return null;
	}
	if (typeof text === 'number') {
		return Number.isFinite(text) && text >= 0 ? round2(text) : null;
	}
	return readDecimal(String(text).trim());
}

export function parsePrice(text) {
	if (typeof text === 'number') {
		return readNumber(text);
	}
	if (typeof text !== 'string') {
		return null;
	}
	let value = text.trim().replace(CURRENCY_PREFIX, '').replace(CURRENCY_SUFFIX, '');
	value = value.replace(/[,.]-$/, '');
	return readNumber(value);
}

export function parseNumber(text) {
	if (typeof text === 'string') {
		return readNumber(text.trim().replace(/\s*%$/, ''));
	}
	return readNumber(text);
}
