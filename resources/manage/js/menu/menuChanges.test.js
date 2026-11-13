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
	rowFromItem, rowsFromItems, newRow, rowFromImport, isBlank, rowStatuses,
	validateRows, moveRow, buildSavePlan, hasChanges,
} from './menuChanges';

const items = [
	{ id: 1, name: 'Pils', description: null, price: 2.5, vat_percentage: 21, is_selling: true, position: 1, category: { id: 7, name: 'Drinks' }, heading: null },
	{ id: 2, name: 'Cola', description: 'fles', price: 2, vat_percentage: null, is_selling: false, position: 2, category: null, heading: null },
	{ id: 3, name: 'Croque', description: null, price: 5, vat_percentage: 6, is_selling: true, position: 3, category: null, heading: null },
];

describe('menuChanges', () => {
	it('gives an empty plan for untouched rows', () => {
		const plan = buildSavePlan(items, rowsFromItems(items));
		expect(plan).toMatchObject({ updates: [], creates: [], deletes: [], sequenceChanged: false });
		expect(hasChanges(items, rowsFromItems(items))).toBe(false);
	});
	it('shows null values as empty text', () => {
		expect(rowFromItem(items[1])).toMatchObject({ description: 'fles', vat_percentage: '', category_id: null, price: '2.00' });
	});
	it('sends name and price plus the changed field', () => {
		const rows = rowsFromItems(items); rows[0].price = '3';
		expect(buildSavePlan(items, rows).updates).toEqual([{ id: 1, changes: { name: 'Pils', price: 3 } }]);
	});
	it('sends only the changed fields besides name and price', () => {
		const rows = rowsFromItems(items); rows[0].name = 'Pils 25cl';
		const changes = buildSavePlan(items, rows).updates[0].changes;
		expect(changes).toEqual({ name: 'Pils 25cl', price: 2.5 });
		['is_selling', 'description', 'vat_percentage', 'category'].forEach(field => {
			expect(changes).not.toHaveProperty(field);
		});
	});
	it('does not send is_selling for a row with a different change', () => {
		const rows = rowsFromItems(items); rows[1].name = 'Cola zero';
		expect(buildSavePlan(items, rows).updates).toEqual([{ id: 2, changes: { name: 'Cola zero', price: 2 } }]);
	});
	it('sends is_selling when only that was flipped', () => {
		const rows = rowsFromItems(items); rows[1].is_selling = true;
		expect(buildSavePlan(items, rows).updates).toEqual([{ id: 2, changes: { name: 'Cola', price: 2, is_selling: true } }]);
	});
	it('treats 2,50 and 2.5 as the same price', () => {
		const rows = rowsFromItems(items); rows[0].price = '2,50';
		expect(buildSavePlan(items, rows).updates).toEqual([]);
	});
	it('sends a category change as an object and a cleared category as null', () => {
		const rows = rowsFromItems(items); rows[0].category_id = null; rows[1].category_id = 7;
		expect(buildSavePlan(items, rows).updates).toEqual([
			{ id: 1, changes: { name: 'Pils', price: 2.5, category: null } },
			{ id: 2, changes: { name: 'Cola', price: 2, category: { id: 7 } } },
		]);
	});
	it('sends a heading change as an object and a cleared heading as null', () => {
		const rows = rowsFromItems(items); rows[0].heading_id = 4;
		expect(buildSavePlan(items, rows).updates).toEqual([{ id: 1, changes: { name: 'Pils', price: 2.5, heading: { id: 4 } } }]);
		const withHeading = items.map(item => (item.id === 1 ? { ...item, heading: { id: 4, name: 'Bieren' } } : item));
		const cleared = rowsFromItems(withHeading); cleared[0].heading_id = null;
		expect(buildSavePlan(withHeading, cleared).updates).toEqual([{ id: 1, changes: { name: 'Pils', price: 2.5, heading: null } }]);
	});
	it('leaves the heading out of the changes of an untouched heading', () => {
		const rows = rowsFromItems(items); rows[0].price = '3';
		expect('heading' in buildSavePlan(items, rows).updates[0].changes).toBe(false);
	});
	it('reads a missing heading key like null', () => {
		const { heading, ...without } = items[0];
		expect(rowFromItem(without).heading_id).toBeNull();
		expect(buildSavePlan([without, ...items.slice(1)], rowsFromItems(items)).updates).toEqual([]);
		expect(rowFromItem({ ...items[0], heading: { id: 4, name: 'Bieren' } }).heading_id).toBe(4);
		expect(newRow().heading_id).toBeNull();
	});
	it('sends a cleared VAT and a cleared description as null', () => {
		const rows = rowsFromItems(items); rows[0].vat_percentage = ''; rows[1].description = '';
		expect(buildSavePlan(items, rows).updates).toEqual([
			{ id: 1, changes: { name: 'Pils', price: 2.5, vat_percentage: null } },
			{ id: 2, changes: { name: 'Cola', price: 2, description: null } },
		]);
	});
	it('creates new rows with all fields and ignores blank rows', () => {
		const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2' }), newRow()];
		const plan = buildSavePlan(items, rows);
		expect(plan.creates).toEqual([{ key: rows[3].key, data: {
			name: 'Thee', description: null, price: 2, vat_percentage: null, is_selling: true, category: null, heading: null } }]);
		expect(plan.sequenceKeys).toEqual([rows[0].key, rows[1].key, rows[2].key, rows[3].key]);
		expect(plan.sequenceChanged).toBe(true);
	});
	it('deletes existing rows and drops deleted new rows', () => {
		const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2', deleted: true })];
		rows[1].deleted = true;
		const plan = buildSavePlan(items, rows);
		expect(plan.deletes).toEqual([2]);
		expect(plan.creates).toEqual([]);
		expect(plan.sequenceKeys).toEqual([rows[0].key, rows[2].key]);
		expect(plan.sequenceChanged).toBe(false);
	});
	it('does not update a row that is deleted', () => {
		const rows = rowsFromItems(items); rows[1].name = 'x'; rows[1].deleted = true;
		expect(buildSavePlan(items, rows).updates).toEqual([]);
	});
	it('flags a moved row', () => {
		const rows = moveRow(rowsFromItems(items), 2, 0);
		const plan = buildSavePlan(items, rows);
		expect(plan.sequenceChanged).toBe(true);
		expect(plan.updates).toEqual([]);
		expect(rowStatuses(items, rows)[rows[0].key]).toBe('moved');
		expect(hasChanges(items, rows)).toBe(true);
	});
	it('returns a new list from moveRow', () => {
		const rows = rowsFromItems(items);
		const moved = moveRow(rows, 0, 2);
		expect(moved).not.toBe(rows);
		expect(rows.map(row => row.id)).toEqual([1, 2, 3]);
		expect(moved.map(row => row.id)).toEqual([2, 3, 1]);
	});
	it('flags a sequence change when an original has no position', () => {
		const unsorted = items.map(item => ({ ...item, position: null }));
		const plan = buildSavePlan(unsorted, rowsFromItems(unsorted));
		expect(plan.sequenceChanged).toBe(true);
		expect(plan.updates).toEqual([]);
		expect(hasChanges(unsorted, rowsFromItems(unsorted))).toBe(true);
	});
	it('ignores a deleted original with no position', () => {
		const mixed = items.map((item, i) => (i === 1 ? { ...item, position: null } : item));
		const rows = rowsFromItems(mixed); rows[1].deleted = true;
		expect(buildSavePlan(mixed, rows).sequenceChanged).toBe(false);
	});
	describe('rowStatuses', () => {
		const five = [1, 2, 3, 4, 5].map(id => ({ id, name: 'P' + id, description: null, price: id, vat_percentage: null, is_selling: true, position: id, category: null }));
		const list = (originals, rows) => {
			const statuses = rowStatuses(originals, rows);
			return rows.map(row => statuses[row.key]);
		};
		it('marks an untouched table clean', () => {
			expect(list(items, rowsFromItems(items))).toEqual(['clean', 'clean', 'clean']);
		});
		it('marks only the dragged row as moved', () => {
			expect(list(items, moveRow(rowsFromItems(items), 2, 0))).toEqual(['moved', 'clean', 'clean']);
		});
		it('marks one row after moving the last row to the top', () => {
			const result = list(five, moveRow(rowsFromItems(five), 4, 0));
			expect(result.filter(s => s === 'moved')).toHaveLength(1);
		});
		it('marks one row after swapping two neighbours', () => {
			const result = list(five, moveRow(rowsFromItems(five), 1, 2));
			expect(result.filter(s => s === 'moved')).toHaveLength(1);
		});
		it('leaves existing rows clean when a new row is inserted at the top', () => {
			const rows = [newRow({ name: 'Thee', price: '2' }), ...rowsFromItems(items)];
			expect(list(items, rows)).toEqual(['new', 'clean', 'clean', 'clean']);
		});
		it('leaves rows after a deleted row clean', () => {
			const rows = rowsFromItems(items); rows[1].deleted = true;
			expect(list(items, rows)).toEqual(['clean', 'deleted', 'clean']);
		});
		it('lets changed win over moved', () => {
			const rows = moveRow(rowsFromItems(items), 2, 0);
			rows[0].price = '9';
			expect(list(items, rows)).toEqual(['changed', 'clean', 'clean']);
		});
		it('reports the status of each row', () => {
			const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2' }), newRow()];
			rows[0].price = '3'; rows[1].deleted = true;
			expect(list(items, rows)).toEqual(['changed', 'deleted', 'clean', 'new', 'blank']);
		});
		it('marks a deleted new row as deleted', () => {
			const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2', deleted: true })];
			expect(list(items, rows)[3]).toBe('deleted');
		});
	});
	it('validates name, price and VAT', () => {
		const rows = rowsFromItems(items);
		rows[0].name = ' '; rows[1].price = 'abc'; rows[2].vat_percentage = 'x';
		rows.push(newRow({ name: 'Thee' }), newRow());
		expect(validateRows(rows)).toEqual([
			{ key: rows[0].key, fields: ['name'] }, { key: rows[1].key, fields: ['price'] },
			{ key: rows[2].key, fields: ['vat_percentage'] }, { key: rows[3].key, fields: ['price'] },
		]);
	});
	it('accepts a price of 0', () => {
		const rows = rowsFromItems(items); rows[0].price = '0';
		expect(validateRows(rows)).toEqual([]);
	});
	it('skips deleted rows in validation', () => {
		const rows = rowsFromItems(items); rows[0].name = ''; rows[0].deleted = true;
		expect(validateRows(rows)).toEqual([]);
	});
	it('knows which rows are blank', () => {
		expect(isBlank(newRow())).toBe(true);
		expect(isBlank(newRow({ name: ' ' }))).toBe(true);
		expect(isBlank(newRow({ price: '1' }))).toBe(false);
		expect(isBlank(rowFromItem(items[0]))).toBe(false);
	});
	it('puts a heading on a row from an import row', () => {
		const importRow = { name: 'Pils', description: '', price: 2.5, vat_percentage: null, errors: [] };
		const row = rowFromImport(importRow, 7, 4);
		expect(row).toMatchObject({ category_id: 7, heading_id: 4 });
		expect(rowFromImport(importRow, 7).heading_id).toBeNull();
		const plan = buildSavePlan([], [row]);
		expect(plan.creates[0].data.heading).toEqual({ id: 4 });
	});
	it('builds a row from an import row', () => {
		expect(rowFromImport({ name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21, errors: [] }, 7))
			.toMatchObject({ id: null, name: 'Pils', description: '25cl', price: '2.50', vat_percentage: '21', category_id: 7, is_selling: true, deleted: false });
	});
});
