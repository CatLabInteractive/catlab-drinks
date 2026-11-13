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
 * Change tracking for the headings of an event: the rows of the Headings
 * section, what changed, and a save runner that sends the renames and the
 * new sequence. Pure functions, no Vue.
 */

import { longestIncreasingRun } from './menuChanges';

let nextKey = 1;

function text(value) {
	return typeof value === 'string' ? value.trim() : '';
}

function byPosition(a, b) {
	const pa = a.position === null || a.position === undefined ? Infinity : a.position;
	const pb = b.position === null || b.position === undefined ? Infinity : b.position;
	return pa === pb ? a.id - b.id : (pa < pb ? -1 : 1);
}

function inSequence(originals) {
	return originals.slice().sort(byPosition);
}

export function headingRowsFrom(headings) {
	return inSequence(headings).map(heading => ({ key: nextKey++, id: heading.id, name: heading.name }));
}

function findOriginal(originals, row) {
	return originals.find(heading => heading.id === row.id) || null;
}

function isRenamed(originals, row) {
	const original = findOriginal(originals, row);
	return !!original && text(original.name) !== text(row.name);
}

function currentRows(originals, rows) {
	return rows.filter(row => findOriginal(originals, row));
}

function sequenceChanged(originals, rows) {
	const before = inSequence(originals).map(heading => heading.id);
	const after = currentRows(originals, rows).map(row => row.id);
	return before.join(',') !== after.join(',');
}

/**
 * 'clean', 'changed' (renamed) or 'moved' (outside the longest run of rows
 * that kept their relative order, so one drag marks one row), by row key.
 */
export function headingStatuses(originals, rows) {
	const rank = new Map();
	inSequence(originals).forEach((heading, i) => rank.set(heading.id, i));
	const existing = currentRows(originals, rows);
	const run = longestIncreasingRun(existing.map(row => rank.get(row.id)));
	const statuses = {};
	existing.forEach((row, i) => {
		if (isRenamed(originals, row)) {
			statuses[row.key] = 'changed';
		} else {
			statuses[row.key] = run.has(i) ? 'clean' : 'moved';
		}
	});
	return statuses;
}

/** Keys of the rows with an empty name. */
export function validateHeadingRows(rows) {
	return rows.filter(row => text(row.name) === '').map(row => row.key);
}

export function hasHeadingChanges(originals, rows) {
	return currentRows(originals, rows).some(row => isRenamed(originals, row))
		|| sequenceChanged(originals, rows);
}

/**
 * Sends one update per renamed heading, then the sequence if it changed.
 * After every request that succeeds, the result is written into copies of
 * the originals and the rows, so that a second run sends only what is left.
 * Never throws.
 */
export async function runHeadingSave(service, originals, rows) {
	let currentOriginals = originals.map(heading => ({ ...heading }));
	let nextRows = rows.map(row => ({ ...row }));
	const renamed = currentRows(currentOriginals, nextRows).filter(row => isRenamed(currentOriginals, row));
	const sequence = sequenceChanged(currentOriginals, nextRows);

	try {
		for (const row of renamed) {
			const name = text(row.name);
			const answer = await service.update(row.id, { name });
			const saved = answer && typeof answer.name === 'string' ? answer.name : name;
			currentOriginals = currentOriginals.map(heading =>
				heading.id === row.id ? { ...heading, name: saved } : heading
			);
			nextRows = nextRows.map(other => (other.id === row.id ? { ...other, name: saved } : other));
		}

		if (sequence) {
			const ids = currentRows(currentOriginals, nextRows).map(row => row.id);
			await service.saveSequence(ids);
			currentOriginals = currentOriginals
				.map(heading => ({ ...heading, position: ids.indexOf(heading.id) + 1 }))
				.sort(byPosition);
		}
	} catch (error) {
		return { originals: currentOriginals, rows: nextRows, error };
	}

	return { originals: currentOriginals, rows: nextRows, error: null };
}
