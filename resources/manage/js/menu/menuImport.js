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
 * Logic behind the menu paste import: splitting pasted lines, guessing what
 * each column holds and turning the sheet into rows that can be imported.
 * Pure functions, no Vue.
 */

import { parsePrice, parseNumber } from './priceParser';

export const COLUMN_TYPES = ['name', 'description', 'price', 'vat_percentage', 'ignore'];

const MAX_PRICE_TOKENS = 3;
const NAME_TRAILER = /[\s.…:\-–—]+$/;

/**
 * Splits a line such as "Pils 25cl ..... 2,50" in a name and a price text.
 * The price text is the longest tail of at most three words that reads as a
 * price. Without such a tail, the price text is empty.
 */
export function smartSplitLine(line) {
	const tokens = String(line).trim().split(/\s+/).filter((token) => token !== '');
	for (let count = Math.min(MAX_PRICE_TOKENS, tokens.length); count >= 1; count--) {
		const priceText = tokens.slice(tokens.length - count).join(' ');
		if (parsePrice(priceText) !== null) {
			const name = tokens.slice(0, tokens.length - count).join(' ').replace(NAME_TRAILER, '');
			return [name, priceText];
		}
	}
	return [tokens.join(' ').replace(NAME_TRAILER, ''), ''];
}

/**
 * A paste of one column is a list of lines: split each one in name and price.
 */
export function prepareSheet(rows) {
	if (rows.length > 0 && rows.every((row) => row.length === 1)) {
		return rows.map((row) => smartSplitLine(row[0]));
	}
	return rows;
}

function cellText(row, index) {
	const cell = row[index];
	return cell === undefined || cell === null ? '' : String(cell).trim();
}

function isPriceLike(rows, index) {
	const cells = rows.map((row) => cellText(row, index)).filter((cell) => cell !== '');
	if (cells.length === 0) {
		return false;
	}
	return cells.filter((cell) => parsePrice(cell) !== null).length > cells.length / 2;
}

/**
 * Guesses one type per column. The last price-like column is the price, the
 * first two other columns with content are name and description.
 */
export function guessColumnTypes(rows) {
	const columnCount = rows.reduce((max, row) => Math.max(max, row.length), 0);
	const types = new Array(columnCount).fill('ignore');
	const priceLike = [];
	for (let index = 0; index < columnCount; index++) {
		priceLike.push(isPriceLike(rows, index));
	}

	const priceIndex = priceLike.lastIndexOf(true);
	if (priceIndex !== -1) {
		types[priceIndex] = 'price';
	}

	const textTypes = ['name', 'description'];
	for (let index = 0; index < columnCount && textTypes.length > 0; index++) {
		if (!priceLike[index] && rows.some((row) => cellText(row, index) !== '')) {
			types[index] = textTypes.shift();
		}
	}
	return types;
}

/**
 * Returns a new list of types. A type other than 'ignore' lives on one
 * column only, so the column that had it before becomes 'ignore'.
 */
export function setColumnType(types, index, type) {
	return types.map((current, i) => {
		if (i === index) {
			return type;
		}
		return type !== 'ignore' && current === type ? 'ignore' : current;
	});
}

export function buildImportRows(rows, types) {
	const cell = (row, type) => {
		const index = types.indexOf(type);
		return index === -1 ? '' : cellText(row, index);
	};
	return rows.map((row) => {
		const name = cell(row, 'name');
		const price = parsePrice(cell(row, 'price'));
		const vat = cell(row, 'vat_percentage');
		const errors = [];
		if (name === '') {
			errors.push('name');
		}
		if (price === null) {
			errors.push('price');
		}
		return {
			name,
			description: cell(row, 'description'),
			price,
			vat_percentage: vat === '' ? null : parseNumber(vat),
			errors,
		};
	});
}

export function canImport(types, importRows) {
	return types.includes('name')
		&& types.includes('price')
		&& importRows.length > 0
		&& importRows.every((row) => row.errors.length === 0);
}
