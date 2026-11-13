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

import { describe, it, expect } from 'vitest';
import {
	headingRowsFrom, headingStatuses, validateHeadingRows, hasHeadingChanges, runHeadingSave,
} from './headingChanges';

const originals = [
	{ id: 1, name: 'Bieren', position: 1 },
	{ id: 2, name: 'Frisdrank', position: 2 },
	{ id: 3, name: 'Snacks', position: 3 },
];

function fakeService({ failUpdate = null, failSequence = false } = {}) {
	const service = {
		calls: [],
		failUpdate,
		failSequence,
		update(id, data) {
			service.calls.push('update ' + id + ' ' + JSON.stringify(data));
			if (service.failUpdate === id) {
				return Promise.reject(new Error('update failed'));
			}
			const original = originals.find(heading => heading.id === id);
			return Promise.resolve({ ...original, ...data });
		},
		saveSequence(ids) {
			service.calls.push('saveSequence ' + ids.join(','));
			return service.failSequence ? Promise.reject(new Error('sequence failed')) : Promise.resolve();
		},
	};
	return service;
}

function changedRows() {
	const rows = headingRowsFrom(originals);
	rows[1].name = 'Fris';
	return [rows[2], rows[0], rows[1]];
}

describe('headingRowsFrom', () => {
	it('builds rows in sequence with a key each', () => {
		const rows = headingRowsFrom([originals[2], originals[0], originals[1]]);
		expect(rows.map(row => [row.id, row.name])).toEqual([[1, 'Bieren'], [2, 'Frisdrank'], [3, 'Snacks']]);
		expect(new Set(rows.map(row => row.key)).size).toBe(3);
	});
});

describe('headingStatuses', () => {
	const list = (rows) => rows.map(row => headingStatuses(originals, rows)[row.key]);
	it('is clean for untouched rows', () => {
		expect(list(headingRowsFrom(originals))).toEqual(['clean', 'clean', 'clean']);
	});
	it('marks one moved row after one drag', () => {
		const rows = headingRowsFrom(originals);
		expect(list([rows[2], rows[0], rows[1]])).toEqual(['moved', 'clean', 'clean']);
	});
	it('marks a renamed row as changed', () => {
		const rows = headingRowsFrom(originals);
		rows[1].name = 'Fris';
		expect(list(rows)).toEqual(['clean', 'changed', 'clean']);
	});
});

describe('validateHeadingRows', () => {
	it('returns the keys of rows with an empty name', () => {
		const rows = headingRowsFrom(originals);
		rows[1].name = '  ';
		expect(validateHeadingRows(rows)).toEqual([rows[1].key]);
		expect(validateHeadingRows(headingRowsFrom(originals))).toEqual([]);
	});
});

describe('hasHeadingChanges', () => {
	it('sees renames and moves, and ignores outer spaces', () => {
		const rows = headingRowsFrom(originals);
		expect(hasHeadingChanges(originals, rows)).toBe(false);
		rows[0].name = ' Bieren ';
		expect(hasHeadingChanges(originals, rows)).toBe(false);
		expect(hasHeadingChanges(originals, changedRows())).toBe(true);
		expect(hasHeadingChanges(originals, [rows[1], rows[0], rows[2]])).toBe(true);
	});
});

describe('runHeadingSave', () => {
	it('sends the rename, then the sequence', async () => {
		const service = fakeService();
		const result = await runHeadingSave(service, originals, changedRows());
		expect(service.calls).toEqual(['update 2 {"name":"Fris"}', 'saveSequence 3,1,2']);
		expect(result.error).toBeNull();
		expect(hasHeadingChanges(result.originals, result.rows)).toBe(false);
		expect(result.originals.map(heading => [heading.id, heading.position])).toEqual([[3, 1], [1, 2], [2, 3]]);
	});
	it('sends nothing for untouched rows', async () => {
		const service = fakeService();
		const result = await runHeadingSave(service, originals, headingRowsFrom(originals));
		expect(service.calls).toEqual([]);
		expect(result.error).toBeNull();
	});
	it('sends no sequence for a rename only', async () => {
		const rows = headingRowsFrom(originals);
		rows[0].name = 'Bier';
		const service = fakeService();
		await runHeadingSave(service, originals, rows);
		expect(service.calls).toEqual(['update 1 {"name":"Bier"}']);
	});
	it('keeps what succeeded after a failure and sends nothing twice', async () => {
		const rows = changedRows();
		rows[1].name = 'Bier';
		const first = fakeService({ failUpdate: 2 });
		const result = await runHeadingSave(first, originals, rows);
		expect(result.error).toBeInstanceOf(Error);
		expect(first.calls).toEqual(['update 1 {"name":"Bier"}', 'update 2 {"name":"Fris"}']);
		const second = fakeService();
		const result2 = await runHeadingSave(second, result.originals, result.rows);
		expect(second.calls).toEqual(['update 2 {"name":"Fris"}', 'saveSequence 3,1,2']);
		expect(result2.error).toBeNull();
	});
	it('does not send a sequence twice if only the sequence request failed', async () => {
		const result = await runHeadingSave(fakeService({ failSequence: true }), originals, changedRows());
		expect(result.error).not.toBeNull();
		const second = fakeService();
		await runHeadingSave(second, result.originals, result.rows);
		expect(second.calls).toEqual(['saveSequence 3,1,2']);
	});
	it('does not mutate its arguments', async () => {
		const rows = changedRows();
		const originalsCopy = JSON.parse(JSON.stringify(originals));
		const rowsCopy = JSON.parse(JSON.stringify(rows));
		await runHeadingSave(fakeService(), originals, rows);
		expect(originals).toEqual(originalsCopy);
		expect(rows).toEqual(rowsCopy);
	});
});
