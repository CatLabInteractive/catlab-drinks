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
import { rowsFromItems, newRow, buildSavePlan, hasChanges } from './menuChanges';
import { runSave } from './menuSave';

const items = [
	{ id: 1, name: 'Pils', description: null, price: 2.5, vat_percentage: 21, is_selling: true, position: 1, category: { id: 7, name: 'Drinks' } },
	{ id: 2, name: 'Cola', description: 'fles', price: 2, vat_percentage: null, is_selling: false, position: 2, category: null },
	{ id: 3, name: 'Croque', description: null, price: 5, vat_percentage: 6, is_selling: true, position: 3, category: null, heading: null },
];

function fakeService({ failUpdate = null, failSequence = false, serverValues = {} } = {}) {
	const service = {
		calls: [],
		args: [],
		failUpdate,
		failSequence,
		// Fields that the server answers with for an item, regardless of what was sent
		// (stands in for a change that a POS device made in the meantime).
		serverValues,
		updateFields(id, changes) {
			service.calls.push('updateFields ' + id);
			service.args.push({ id, changes });
			if (service.failUpdate === id) {
				return Promise.reject(new Error('update failed'));
			}
			const original = items.find(item => item.id === id);
			return Promise.resolve({ ...original, ...changes, ...(service.serverValues[id] || {}) });
		},
		bulkCreate(dataList) {
			service.calls.push('bulkCreate ' + dataList.length);
			service.args.push({ dataList });
			return Promise.resolve(dataList.map((data, i) => ({ ...data, id: 100 + i })));
		},
		delete(id) {
			service.calls.push('delete ' + id);
			return Promise.resolve();
		},
		saveSequence(ids) {
			service.calls.push('saveSequence ' + ids.join(','));
			return service.failSequence ? Promise.reject(new Error('sequence failed')) : Promise.resolve();
		},
	};
	return service;
}

describe('runSave', () => {
	it('runs updates, creates, deletes, then the sequence', async () => {
		const rows = rowsFromItems(items);
		rows[0].price = '3';
		rows[1].deleted = true;
		rows.push(newRow({ name: 'Thee', price: '2' }));
		const service = fakeService();
		const result = await runSave(service, items, rows);
		expect(service.calls).toEqual(['updateFields 1', 'bulkCreate 1', 'delete 2', 'saveSequence 1,3,100']);
		expect(service.args[0]).toEqual({ id: 1, changes: { name: 'Pils', price: 3 } });
		expect(service.args[1].dataList[0]).toMatchObject({ name: 'Thee', price: 2, category: null });
		expect(result.error).toBeNull();
		expect(hasChanges(result.originals, result.rows)).toBe(false);
		expect(result.rows.find(row => row.id === 2)).toBeUndefined();
		expect(result.rows.find(row => row.name === 'Thee').id).toBe(100);
	});
	it('does not call the sequence endpoint if the sequence is unchanged', async () => {
		const rows = rowsFromItems(items);
		rows[0].price = '3';
		const service = fakeService();
		await runSave(service, items, rows);
		expect(service.calls).toEqual(['updateFields 1']);
	});
	it('makes no calls for an untouched table', async () => {
		const service = fakeService();
		const result = await runSave(service, items, rowsFromItems(items));
		expect(service.calls).toEqual([]);
		expect(result.error).toBeNull();
	});
	it('keeps what succeeded after a failure and does not send it again', async () => {
		const rows = rowsFromItems(items);
		rows[0].price = '3';
		rows[2].price = '6';
		rows.push(newRow({ name: 'Thee', price: '2' }));
		const service = fakeService({ failUpdate: 3 });
		const result = await runSave(service, items, rows);
		expect(result.error).toBeInstanceOf(Error);
		expect(service.calls).toEqual(['updateFields 1', 'updateFields 3']);
		const plan = buildSavePlan(result.originals, result.rows);
		expect(plan.updates.map(update => update.id)).toEqual([3]);
		expect(plan.creates.map(create => create.data.name)).toEqual(['Thee']);

		const second = fakeService();
		const result2 = await runSave(second, result.originals, result.rows);
		expect(second.calls).toEqual(['updateFields 3', 'bulkCreate 1', 'saveSequence 1,2,3,100']);
		expect(result2.error).toBeNull();
	});
	it('does not create products twice if the sequence request fails', async () => {
		const rows = rowsFromItems(items);
		rows.push(newRow({ name: 'Thee', price: '2' }));
		const result = await runSave(fakeService({ failSequence: true }), items, rows);
		expect(result.error).not.toBeNull();
		expect(result.rows.find(row => row.name === 'Thee').id).toBe(100);
		expect(buildSavePlan(result.originals, result.rows).creates).toEqual([]);
		const second = fakeService();
		await runSave(second, result.originals, result.rows);
		expect(second.calls).toEqual(['saveSequence 1,2,3,100']);
	});
	it('agrees with the server answer for a product with a heading', async () => {
		const heading = { id: 4, name: 'Bieren', position: 1 };
		const originals = items.map(item => (item.id === 1 ? { ...item, heading } : item));
		const rows = rowsFromItems(originals);
		rows[0].price = '3';
		rows[2].heading_id = 4;
		const result = await runSave(fakeService({ serverValues: { 1: { heading }, 3: { heading } } }), originals, rows);
		expect(result.error).toBeNull();
		expect(result.rows.map(row => row.heading_id)).toEqual([4, null, 4]);
		expect(hasChanges(result.originals, result.rows)).toBe(false);
	});
	it('does not mutate its arguments', async () => {
		const rows = rowsFromItems(items);
		rows[0].price = '3';
		rows[1].deleted = true;
		rows.push(newRow({ name: 'Thee', price: '2' }));
		const originalsCopy = JSON.parse(JSON.stringify(items));
		const rowsCopy = JSON.parse(JSON.stringify(rows));
		await runSave(fakeService(), items, rows);
		expect(items).toEqual(originalsCopy);
		expect(rows).toEqual(rowsCopy);
	});
	it.each([
		[true, false],
		[false, true],
	])('takes over is_selling from the server answer (page %s, server %s)', async (pageValue, serverValue) => {
		const originals = items.map(item => (item.id === 1 ? { ...item, is_selling: pageValue } : item));
		const rows = rowsFromItems(originals);
		rows[0].price = '3';
		const keyBefore = rows[0].key;
		const service = fakeService({ serverValues: { 1: { is_selling: serverValue } } });
		const result = await runSave(service, originals, rows);
		expect(result.error).toBeNull();
		expect(service.args[0]).toEqual({ id: 1, changes: { name: 'Pils', price: 3 } });
		expect(hasChanges(result.originals, result.rows)).toBe(false);
		const row = result.rows.find(candidate => candidate.id === 1);
		expect(row.is_selling).toBe(serverValue);
		expect(row.key).toBe(keyBefore);
		expect(row.price).toBe('3.00');

		const second = fakeService({ serverValues: { 1: { is_selling: serverValue } } });
		await runSave(second, result.originals, result.rows);
		expect(second.calls).toEqual([]);
	});
	it('takes over the values of the server answer for a created row', async () => {
		const rows = rowsFromItems(items);
		rows.push(newRow({ name: 'Thee', price: '2', description: '  ' }));
		const keyBefore = rows[3].key;
		const service = fakeService();
		service.bulkCreate = dataList => {
			service.calls.push('bulkCreate ' + dataList.length);
			return Promise.resolve(dataList.map((data, i) => ({ ...data, id: 100 + i, is_selling: false })));
		};
		const result = await runSave(service, items, rows);
		expect(result.error).toBeNull();
		const row = result.rows.find(candidate => candidate.id === 100);
		expect(row.key).toBe(keyBefore);
		expect(row.is_selling).toBe(false);
		expect(hasChanges(result.originals, result.rows)).toBe(false);
	});
	it('drops a typed and then deleted new row after the save', async () => {
		const rows = rowsFromItems(items);
		rows[0].price = '3';
		rows.push(newRow({ name: 'Thee', price: '2', deleted: true }));
		const result = await runSave(fakeService(), items, rows);
		expect(result.error).toBeNull();
		expect(result.rows.find(row => row.name === 'Thee')).toBeUndefined();
		expect(hasChanges(result.originals, result.rows)).toBe(false);
	});
	it('drops a typed and then deleted new row also when no request is needed', async () => {
		const rows = rowsFromItems(items);
		rows.push(newRow({ name: 'Thee', price: '2', deleted: true }));
		const service = fakeService();
		const result = await runSave(service, items, rows);
		expect(service.calls).toEqual([]);
		expect(result.rows).toHaveLength(3);
		expect(hasChanges(result.originals, result.rows)).toBe(false);
	});
});
