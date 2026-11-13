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
 * Change tracking for the editable menu table: turns the rows of the table
 * into the list of requests that Save has to send. Pure functions, no Vue.
 */

import { parsePrice, parseNumber } from './priceParser';

let nextKey = 1;

function text(value) {
	return typeof value === 'string' ? value.trim() : '';
}

function textOrNull(value) {
	const trimmed = text(value);
	return trimmed === '' ? null : trimmed;
}

function formatNumber(value) {
	return value === null || value === undefined ? '' : String(value);
}

export function newRow(values = {}) {
	return {
		key: nextKey++,
		id: null,
		name: '',
		description: '',
		price: '',
		vat_percentage: '',
		category_id: null,
		heading_id: null,
		is_selling: true,
		deleted: false,
		...values,
	};
}

export function rowFromItem(item) {
	return newRow({
		id: item.id,
		name: item.name,
		description: item.description || '',
		price: item.price === null || item.price === undefined ? '' : Number(item.price).toFixed(2),
		vat_percentage: formatNumber(item.vat_percentage),
		category_id: item.category ? item.category.id : null,
		heading_id: item.heading ? item.heading.id : null,
		is_selling: !!item.is_selling,
	});
}

export function rowsFromItems(items) {
	return items.map(rowFromItem);
}

export function rowFromImport(importRow, categoryId, headingId = null) {
	return newRow({
		name: importRow.name,
		description: importRow.description || '',
		price: importRow.price === null || importRow.price === undefined ? '' : importRow.price.toFixed(2),
		vat_percentage: formatNumber(importRow.vat_percentage),
		category_id: categoryId,
		heading_id: headingId,
	});
}

export function isBlank(row) {
	return row.id === null && text(row.name) === '' && text(row.description) === '' && text(row.price) === '';
}

function isActive(row) {
	return !row.deleted && !isBlank(row);
}

function originalsInSequence(originals) {
	return originals
		.map((item, order) => ({ item, order }))
		.sort((a, b) => {
			if (a.item.position === null || a.item.position === undefined) {
				return (b.item.position === null || b.item.position === undefined) ? a.order - b.order : 1;
			}
			if (b.item.position === null || b.item.position === undefined) {
				return -1;
			}
			return a.item.position - b.item.position || a.order - b.order;
		})
		.map(entry => entry.item);
}

function findOriginal(originals, row) {
	return originals.find(item => item.id === row.id) || null;
}

/**
 * Normalized values of a row, the form in which rows and originals are
 * compared and in which values are sent.
 */
function normalizedRow(row) {
	return {
		name: text(row.name),
		description: textOrNull(row.description),
		price: parsePrice(row.price),
		vat_percentage: parseNumber(row.vat_percentage),
		is_selling: !!row.is_selling,
		category_id: row.category_id === undefined ? null : row.category_id,
		heading_id: row.heading_id === undefined ? null : row.heading_id,
	};
}

function normalizedItem(item) {
	return {
		name: text(item.name),
		description: textOrNull(item.description),
		price: parsePrice(item.price),
		vat_percentage: parseNumber(item.vat_percentage),
		is_selling: !!item.is_selling,
		category_id: item.category ? item.category.id : null,
		heading_id: item.heading ? item.heading.id : null,
	};
}

function categoryValue(categoryId) {
	return categoryId === null ? null : { id: categoryId };
}

/**
 * The changes of one row. Always carries name and price, because the
 * update endpoint requires them, plus the fields that the user changed.
 */
function changesFor(original, row) {
	const before = normalizedItem(original);
	const after = normalizedRow(row);
	const changed = ['description', 'vat_percentage', 'is_selling', 'category_id', 'heading_id']
		.filter(field => before[field] !== after[field]);
	const changedName = before.name !== after.name;
	const changedPrice = before.price !== after.price;
	if (!changed.length && !changedName && !changedPrice) {
		return null;
	}
	const changes = { name: after.name, price: after.price };
	changed.forEach(field => {
		if (field === 'category_id') {
			changes.category = categoryValue(after[field]);
		} else if (field === 'heading_id') {
			changes.heading = categoryValue(after[field]);
		} else {
			changes[field] = after[field];
		}
	});
	return changes;
}

/**
 * Indexes of the longest strictly increasing run in a list of ranks. When
 * several runs are equally long, the one that ends first wins.
 */
export function longestIncreasingRun(ranks) {
	const length = [];
	const previous = [];
	let best = -1;
	ranks.forEach((rank, i) => {
		length[i] = 1;
		previous[i] = -1;
		for (let j = 0; j < i; j++) {
			if (ranks[j] < rank && length[j] + 1 > length[i]) {
				length[i] = length[j] + 1;
				previous[i] = j;
			}
		}
		if (best === -1 || length[i] > length[best]) {
			best = i;
		}
	});
	const run = new Set();
	for (let i = best; i !== -1; i = previous[i]) {
		run.add(i);
	}
	return run;
}

/**
 * The status of every row, keyed by row key. A row is only 'moved' when it
 * is outside the longest run of existing rows that kept their relative
 * order, so one drag marks one row.
 */
export function rowStatuses(originals, rows) {
	const statuses = {};
	const rank = new Map();
	originalsInSequence(originals)
		.sort((a, b) => {
			const pa = a.position === null || a.position === undefined ? Infinity : a.position;
			const pb = b.position === null || b.position === undefined ? Infinity : b.position;
			return pa === pb ? a.id - b.id : (pa < pb ? -1 : 1);
		})
		.forEach((item, i) => rank.set(item.id, i));

	const existing = [];
	rows.forEach(row => {
		if (row.id === null && isBlank(row)) {
			statuses[row.key] = 'blank';
		} else if (row.deleted) {
			statuses[row.key] = 'deleted';
		} else if (row.id === null || !findOriginal(originals, row)) {
			statuses[row.key] = 'new';
		} else {
			existing.push(row);
		}
	});

	const run = longestIncreasingRun(existing.map(row => rank.get(row.id)));
	existing.forEach((row, i) => {
		if (changesFor(findOriginal(originals, row), row)) {
			statuses[row.key] = 'changed';
		} else {
			statuses[row.key] = run.has(i) ? 'clean' : 'moved';
		}
	});
	return statuses;
}

export function validateRows(rows) {
	const problems = [];
	rows.forEach(row => {
		if (!isActive(row)) {
			return;
		}
		const fields = [];
		if (text(row.name) === '') {
			fields.push('name');
		}
		if (parsePrice(row.price) === null) {
			fields.push('price');
		}
		if (text(row.vat_percentage) !== '' && parseNumber(row.vat_percentage) === null) {
			fields.push('vat_percentage');
		}
		if (fields.length) {
			problems.push({ key: row.key, fields });
		}
	});
	return problems;
}

export function moveRow(rows, from, to) {
	const result = rows.slice();
	const [row] = result.splice(from, 1);
	result.splice(to, 0, row);
	return result;
}

export function buildSavePlan(originals, rows) {
	const updates = [];
	const creates = [];
	const deletes = [];
	const sequenceKeys = [];
	const remainingIds = [];

	rows.forEach(row => {
		const original = row.id === null ? null : findOriginal(originals, row);
		if (row.deleted) {
			if (original) {
				deletes.push(original.id);
			}
			return;
		}
		if (!original) {
			if (isBlank(row)) {
				return;
			}
			const values = normalizedRow(row);
			creates.push({
				key: row.key,
				data: {
					name: values.name,
					description: values.description,
					price: values.price,
					vat_percentage: values.vat_percentage,
					is_selling: values.is_selling,
					category: categoryValue(values.category_id),
					heading: categoryValue(values.heading_id),
				},
			});
			sequenceKeys.push(row.key);
			return;
		}
		const changes = changesFor(original, row);
		if (changes) {
			updates.push({ id: original.id, changes });
		}
		remainingIds.push(original.id);
		sequenceKeys.push(row.key);
	});

	const remainingOriginals = originalsInSequence(originals).filter(item => !deletes.includes(item.id));
	const sequenceChanged = creates.length > 0
		|| remainingOriginals.some(item => item.position === null || item.position === undefined)
		|| remainingOriginals.map(item => item.id).join(',') !== remainingIds.join(',');

	return { updates, creates, deletes, sequenceKeys, sequenceChanged };
}

export function hasChanges(originals, rows) {
	const plan = buildSavePlan(originals, rows);
	return plan.updates.length > 0 || plan.creates.length > 0 || plan.deletes.length > 0 || plan.sequenceChanged;
}
