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

export const COLUMN_TYPES = ['name', 'description', 'price', 'vat_percentage', 'category', 'heading', 'ignore'];

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
			category: cell(row, 'category'),
			heading: cell(row, 'heading'),
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

function normalizedName(name) {
	return String(name === undefined || name === null ? '' : name).trim().toLowerCase();
}

/**
 * The entry of the list with this name, ignoring letter case and outer
 * spaces. An empty name matches nothing.
 */
export function matchByName(name, list) {
	const wanted = normalizedName(name);
	if (wanted === '') {
		return null;
	}
	return list.find((entry) => normalizedName(entry.name) === wanted) || null;
}

/**
 * The names of the import rows (field 'category' or 'heading') that match
 * nothing in the existing list, each once (the first spelling wins). Names
 * of preferredOrder come first, in that order, then the others in order of
 * first appearance.
 */
export function namesToCreate(importRows, field, existing, preferredOrder = []) {
	const names = [];
	const seen = new Set();
	importRows.forEach((row) => {
		const name = String(row[field] || '').trim();
		const key = normalizedName(name);
		if (key === '' || seen.has(key) || matchByName(name, existing)) {
			return;
		}
		seen.add(key);
		names.push(name);
	});
	const rank = (name) => {
		const index = preferredOrder.findIndex((preferred) => normalizedName(preferred) === normalizedName(name));
		return index === -1 ? Infinity : index;
	};
	return names
		.map((name, order) => ({ name, order }))
		.sort((a, b) => (rank(a.name) === rank(b.name) ? a.order - b.order : (rank(a.name) < rank(b.name) ? -1 : 1)))
		.map((entry) => entry.name);
}

/**
 * The distinct heading names of the items, in heading position, then
 * heading id order.
 */
export function headingOrderFromItems(items) {
	const headings = new Map();
	items.forEach((item) => {
		if (item.heading && !headings.has(item.heading.id)) {
			headings.set(item.heading.id, item.heading);
		}
	});
	return Array.from(headings.values())
		.sort((a, b) => (a.position - b.position) || (a.id - b.id))
		.map((heading) => heading.name);
}

const COPY_TYPES = ['name', 'description', 'price', 'vat_percentage'];

const OPTIONAL_COLUMNS = [
	['description', (item) => item.description],
	['vat_percentage', (item) => (item.vat_percentage === undefined || item.vat_percentage === null ? '' : String(Number(item.vat_percentage)))],
	['category', (item) => (item.category ? item.category.name : '')],
	['heading', (item) => (item.heading ? item.heading.name : '')],
];

/**
 * Turns the products of a menu into a sheet, so that they can be imported
 * like a paste: name, description, price with two decimals, VAT, category
 * and heading. A column that is empty for all products is left out; name and
 * price always stay.
 */
export function sheetFromItems(items) {
	const text = (value) => (value === undefined || value === null ? '' : String(value));
	if (items.length === 0) {
		return { cells: [], types: COPY_TYPES.slice() };
	}
	const columns = [
		['name', (item) => item.name],
		['description', OPTIONAL_COLUMNS[0][1]],
		['price', (item) => Number(item.price).toFixed(2)],
		...OPTIONAL_COLUMNS.slice(1),
	].filter(([type, read]) => !OPTIONAL_COLUMNS.some(([optional]) => optional === type)
		|| items.some((item) => text(read(item)) !== ''));
	return {
		cells: items.map((item) => columns.map(([, read]) => text(read(item)))),
		types: columns.map(([type]) => type),
	};
}
