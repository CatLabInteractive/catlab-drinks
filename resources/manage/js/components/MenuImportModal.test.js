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
 * Tests for the logic of the import dialog. Vitest does not compile SFCs
 * here: the `vue-script-only` plugin in vitest.config.js makes an import of a
 * .vue file give the options of its <script> block. The test runs its data,
 * computed values and methods on a plain object. The template and
 * Bootstrap-Vue are not involved.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import component from './MenuImportModal.vue';

const menuIndex = vi.fn();

vi.mock('../../../shared/js/services/MenuService', () => ({
	MenuService: class {
		constructor(eventId) {
			this.eventId = eventId;
		}

		index() {
			return menuIndex(this.eventId);
		}
	}
}));

function deferred() {
	let resolveFn, rejectFn;
	const promise = new Promise((res, rej) => {
		resolveFn = res;
		rejectFn = rej;
	});
	return { promise, resolve: resolveFn, reject: rejectFn };
}

/**
 * A component instance without Vue: props, data, computed values as
 * getters, and bound methods. The parents' create functions add the new
 * entry to the list that the prop carries, as Menu.vue does.
 */
function makeVm(props = {}) {
	const vm = {
		categories: [],
		headings: [],
		otherEvents: [],
		createCategory: null,
		createHeading: null,
		...props
	};
	Object.assign(vm, component.data.call(vm));
	Object.keys(component.computed).forEach((key) => {
		Object.defineProperty(vm, key, { get: () => component.computed[key].call(vm) });
	});
	Object.keys(component.methods).forEach((key) => {
		vm[key] = component.methods[key].bind(vm);
	});
	vm.$t = (key) => key;
	vm.$emit = vi.fn();
	vm.hiddenCount = 0;
	vm.$refs = {
		modal: {
			show() {},
			hide() {
				let prevented = false;
				vm.onHide({ preventDefault: () => { prevented = true; } });
				if (!prevented) {
					vm.hiddenCount++;
					vm.onHidden();
				}
			}
		}
	};
	return vm;
}

/**
 * Create functions that behave like the ones of Menu.vue: create at once,
 * add to the list, reject on failure. `failOn` names reject.
 */
function creators(vm, { failOn = [] } = {}) {
	let nextId = 100;
	const make = (listName) => vi.fn(async (name) => {
		if (failOn.includes(name)) {
			const error = new Error('Request failed');
			error.response = { status: 422, data: { error: { status: 422, message: 'Name is taken: ' + name } } };
			throw error;
		}
		const entry = { id: nextId++, name };
		vm[listName].push(entry);
		return entry;
	});
	vm.createCategory = make('categories');
	vm.createHeading = make('headings');
}

function sheet(vm, cells, types) {
	vm.cells = cells;
	vm.types = types;
}

function emittedRows(vm) {
	expect(vm.$emit).toHaveBeenCalledTimes(1);
	expect(vm.$emit.mock.calls[0][0]).toBe('import');
	return vm.$emit.mock.calls[0][1];
}

beforeEach(() => {
	menuIndex.mockReset();
	vi.stubGlobal('prompt', vi.fn());
	vi.stubGlobal('confirm', vi.fn(() => true));
});

describe('MenuImportModal column types', () => {
	it('has a label for every column type, "Station" for a category', () => {
		const labels = component.data.call({}).typeLabels;
		const types = component.data.call({}).columnTypes;
		types.forEach((type) => expect(labels[type]).toBeTruthy());
		expect(labels.category).toBe('Station');
		expect(labels.heading).toBe('Heading');
	});

	it('keeps a category or heading column on one column at most', () => {
		const vm = makeVm();
		sheet(vm, [['a', 'b', 'c', '1']], ['heading', 'ignore', 'name', 'price']);
		vm.changeType(1, 'heading');
		expect(vm.types).toEqual(['ignore', 'heading', 'name', 'price']);
	});

	it('never marks a row for its category or heading name', () => {
		const vm = makeVm();
		sheet(vm, [['Pils', '2,50', '', '']], ['name', 'price', 'category', 'heading']);
		expect(vm.hasErrors).toBe(false);
		expect(vm.importable).toBe(true);
	});
});

describe('MenuImportModal Add', () => {
	it('creates each new heading once (letter case does not count) and links the rows', async () => {
		const vm = makeVm();
		creators(vm);
		sheet(vm, [
			['Pils', '2,50', 'Bieren'],
			['Kriek', '3,00', 'bieren'],
			['Cola', '2,00', 'Frisdrank']
		], ['name', 'price', 'heading']);

		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Bieren', 'Frisdrank']);
		expect(vm.createCategory).not.toHaveBeenCalled();
		const rows = emittedRows(vm);
		const bieren = vm.headings.find(heading => heading.name === 'Bieren').id;
		const frisdrank = vm.headings.find(heading => heading.name === 'Frisdrank').id;
		expect(rows.map(row => row.heading_id)).toEqual([bieren, bieren, frisdrank]);
		expect(rows.map(row => row.category_id)).toEqual([null, null, null]);
		expect(rows.map(row => row.name)).toEqual(['Pils', 'Kriek', 'Cola']);
		expect(vm.hiddenCount).toBe(1);
	});

	it('uses existing categories and headings by name, and the selection for all on an empty cell', async () => {
		const vm = makeVm({
			categories: [{ id: 1, name: 'Bar' }, { id: 2, name: 'Keuken' }],
			headings: [{ id: 7, name: 'Bieren', position: 1 }, { id: 8, name: 'Snacks', position: 2 }]
		});
		creators(vm);
		vm.categoryId = 1;
		vm.headingId = 8;
		sheet(vm, [
			['Pils', '2,50', ' keuken ', 'BIEREN '],
			['Chips', '1,50', '', '']
		], ['name', 'price', 'category', 'heading']);

		await vm.submit();

		expect(vm.createCategory).not.toHaveBeenCalled();
		expect(vm.createHeading).not.toHaveBeenCalled();
		const rows = emittedRows(vm);
		expect(rows.map(row => [row.category_id, row.heading_id])).toEqual([[2, 7], [1, 8]]);
	});

	it('creates new categories and headings before it adds rows', async () => {
		const vm = makeVm({ categories: [{ id: 1, name: 'Bar' }] });
		creators(vm);
		sheet(vm, [
			['Pils', '2,50', 'Bar', 'Bieren'],
			['Frietjes', '4,00', 'Keuken', 'Eten']
		], ['name', 'price', 'category', 'heading']);

		await vm.submit();

		expect(vm.createCategory.mock.calls.map(call => call[0])).toEqual(['Keuken']);
		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Bieren', 'Eten']);
		const keuken = vm.categories.find(category => category.name === 'Keuken').id;
		const rows = emittedRows(vm);
		expect(rows.map(row => row.category_id)).toEqual([1, keuken]);
	});

	it('creates one request at a time', async () => {
		const vm = makeVm();
		const pending = [];
		vm.createHeading = vi.fn((name) => {
			const request = deferred();
			pending.push({ name, request });
			return request.promise;
		});
		sheet(vm, [['Pils', '2,50', 'Bieren'], ['Cola', '2,00', 'Frisdrank']], ['name', 'price', 'heading']);

		const done = vm.submit();
		await Promise.resolve();
		expect(pending.map(entry => entry.name)).toEqual(['Bieren']);

		const bieren = { id: 1, name: 'Bieren' };
		vm.headings.push(bieren);
		pending[0].request.resolve(bieren);
		await vi.waitFor(() => expect(pending.length).toBe(2));
		const frisdrank = { id: 2, name: 'Frisdrank' };
		vm.headings.push(frisdrank);
		pending[1].request.resolve(frisdrank);
		await done;

		expect(emittedRows(vm).map(row => row.heading_id)).toEqual([1, 2]);
	});

	it('blocks a second click, closing, and editing while the requests run', async () => {
		const vm = makeVm();
		const request = deferred();
		vm.createHeading = vi.fn(() => request.promise);
		sheet(vm, [['Pils', '2,50', 'Bieren']], ['name', 'price', 'heading']);

		const first = vm.submit();
		expect(vm.adding).toBe(true);
		await vm.submit();
		expect(vm.createHeading).toHaveBeenCalledTimes(1);

		vm.hide();
		expect(vm.hiddenCount).toBe(0);

		const entry = { id: 3, name: 'Bieren' };
		vm.headings.push(entry);
		request.resolve(entry);
		await first;
		expect(vm.adding).toBe(false);
		expect(emittedRows(vm)[0].heading_id).toBe(3);
		expect(vm.hiddenCount).toBe(1);
	});

	it('prevents a close through the header X or Cancel while the requests run', async () => {
		const vm = makeVm();
		const request = deferred();
		vm.createHeading = vi.fn(() => request.promise);
		sheet(vm, [['Pils', '2,50', 'Bieren']], ['name', 'price', 'heading']);

		const done = vm.submit();
		expect(vm.adding).toBe(true);
		const preventDefault = vi.fn();
		vm.onHide({ trigger: 'headerclose', preventDefault });
		expect(preventDefault).toHaveBeenCalledTimes(1);
		expect(confirm).not.toHaveBeenCalled();

		const entry = { id: 3, name: 'Bieren' };
		vm.headings.push(entry);
		request.resolve(entry);
		await done;
	});

	it('stops at a failed create, shows the message and adds nothing; a second Add creates only the rest', async () => {
		const vm = makeVm();
		creators(vm, { failOn: ['Frisdrank'] });
		sheet(vm, [
			['Pils', '2,50', 'Bieren'],
			['Cola', '2,00', 'Frisdrank'],
			['Spa', '2,00', 'Water']
		], ['name', 'price', 'heading']);

		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Bieren', 'Frisdrank']);
		expect(vm.$emit).not.toHaveBeenCalled();
		expect(vm.hiddenCount).toBe(0);
		expect(vm.adding).toBe(false);
		expect(vm.addMessage).toBe('Name is taken: Frisdrank');
		expect(vm.cells.length).toBe(3);

		creators(vm);
		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Frisdrank', 'Water']);
		expect(vm.headings.filter(heading => heading.name === 'Bieren').length).toBe(1);
		expect(vm.addMessage).toBe(null);
		expect(emittedRows(vm).length).toBe(3);
	});

	it('does not emit when the dialog was opened again during the requests', async () => {
		const vm = makeVm();
		const request = deferred();
		vm.createHeading = vi.fn(() => request.promise);
		sheet(vm, [['Pils', '2,50', 'Bieren']], ['name', 'price', 'heading']);

		const done = vm.submit();
		await vi.waitFor(() => expect(vm.createHeading).toHaveBeenCalled());
		vm.show();
		expect(vm.adding).toBe(false);
		request.resolve({ id: 1, name: 'Bieren' });
		await done;

		expect(vm.$emit).not.toHaveBeenCalled();
		expect(vm.adding).toBe(false);
	});

	it('does not show an old error after the dialog was opened again during a failing request', async () => {
		const vm = makeVm();
		const request = deferred();
		vm.createHeading = vi.fn(() => request.promise);
		sheet(vm, [['Pils', '2,50', 'Bieren']], ['name', 'price', 'heading']);

		const done = vm.submit();
		await vi.waitFor(() => expect(vm.createHeading).toHaveBeenCalled());
		vm.show();
		request.reject(new Error('Network down'));
		await done;

		expect(vm.addMessage).toBe(null);
		expect(vm.$emit).not.toHaveBeenCalled();
	});

	it('still adds plain rows with the selections for all', async () => {
		const vm = makeVm({ categories: [{ id: 4, name: 'Bar' }], headings: [{ id: 5, name: 'Bieren', position: 1 }] });
		creators(vm);
		vm.categoryId = 4;
		vm.headingId = 5;
		sheet(vm, [['Pils', '2,50'], ['', ''], ['Kriek', '3']], ['name', 'price']);

		await vm.submit();

		const rows = emittedRows(vm);
		expect(rows.map(row => [row.name, row.price, row.category_id, row.heading_id]))
			.toEqual([['Pils', '2.50', 4, 5], ['Kriek', '3.00', 4, 5]]);
	});
});

describe('MenuImportModal copy from a different event', () => {
	const sourceItems = [
		{ id: 1, name: 'Pils', price: 2.5, category: { id: 11, name: 'Bar' }, heading: { id: 21, name: 'Bieren', position: 2 } },
		{ id: 2, name: 'Cola', price: 2, category: { id: 11, name: 'Bar' }, heading: { id: 22, name: 'Frisdrank', position: 1 } },
		{ id: 3, name: 'Frietjes', price: 4, category: { id: 12, name: 'Keuken' } }
	];

	it('fills the sheet with the category and heading names of the source', async () => {
		menuIndex.mockResolvedValue({ items: sourceItems });
		const vm = makeVm();

		await vm.copyFromEvent({ value: '9' });

		expect(menuIndex).toHaveBeenCalledWith('9');
		expect(vm.types).toEqual(['name', 'price', 'category', 'heading']);
		expect(vm.cells.map(row => row.slice(2))).toEqual([['Bar', 'Bieren'], ['Bar', 'Frisdrank'], ['Keuken', '']]);
	});

	it('creates the new headings in the heading sequence of the source', async () => {
		menuIndex.mockResolvedValue({ items: sourceItems });
		const vm = makeVm();
		creators(vm);
		await vm.copyFromEvent({ value: '9' });

		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Frisdrank', 'Bieren']);
		expect(vm.createCategory.mock.calls.map(call => call[0])).toEqual(['Bar', 'Keuken']);
		const rows = emittedRows(vm);
		const id = (list, name) => vm[list].find(entry => entry.name === name).id;
		expect(rows.map(row => [row.category_id, row.heading_id])).toEqual([
			[id('categories', 'Bar'), id('headings', 'Bieren')],
			[id('categories', 'Bar'), id('headings', 'Frisdrank')],
			[id('categories', 'Keuken'), null]
		]);
	});

	it('forgets the heading sequence of the source when a paste replaces the sheet', async () => {
		menuIndex.mockResolvedValue({ items: sourceItems });
		const vm = makeVm();
		creators(vm);
		await vm.copyFromEvent({ value: '9' });

		vm.cells = [['', '']];
		vm.types = ['name', 'price'];
		vm.onPaste({
			clipboardData: { getData: () => 'Pils\t2,50\tBieren\nCola\t2,00\tFrisdrank' },
			preventDefault() {}
		}, 0, 0);
		vm.changeType(2, 'heading');
		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Bieren', 'Frisdrank']);
	});

	it('keeps the heading sequence of the source when a paste writes into the copied sheet', async () => {
		menuIndex.mockResolvedValue({ items: sourceItems });
		const vm = makeVm();
		creators(vm);
		await vm.copyFromEvent({ value: '9' });

		vm.onPaste({
			clipboardData: { getData: () => 'Pils\t2,50\nCola\t2,00' },
			preventDefault() {}
		}, 0, 0);
		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Frisdrank', 'Bieren']);
	});

	it('forgets the heading sequence of the source when the dialog is opened again', async () => {
		menuIndex.mockResolvedValue({ items: sourceItems });
		const vm = makeVm();
		creators(vm);
		await vm.copyFromEvent({ value: '9' });
		vm.hide();

		vm.show();
		sheet(vm, [['Pils', '2,50', 'Bieren'], ['Cola', '2,00', 'Frisdrank']], ['name', 'price', 'heading']);
		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Bieren', 'Frisdrank']);
	});

	it('forgets the heading sequence of an earlier copy when a copy finds no products', async () => {
		menuIndex.mockResolvedValue({ items: sourceItems });
		const vm = makeVm();
		creators(vm);
		await vm.copyFromEvent({ value: '9' });

		vm.cells = [['', '']];
		vm.types = ['name', 'price'];
		menuIndex.mockResolvedValue({ items: [] });
		await vm.copyFromEvent({ value: '10' });
		expect(vm.copyMessage).toBe('This event has no products.');

		sheet(vm, [['Pils', '2,50', 'Bieren'], ['Cola', '2,00', 'Frisdrank']], ['name', 'price', 'heading']);
		await vm.submit();

		expect(vm.createHeading.mock.calls.map(call => call[0])).toEqual(['Bieren', 'Frisdrank']);
	});
});

describe('MenuImportModal selectors for all products', () => {
	function changeEvent(vm, field, value) {
		vm[field] = value;
		return { target: { selectedIndex: -1 } };
	}

	it('creates a new heading at once and selects it', async () => {
		const vm = makeVm({ headings: [{ id: 1, name: 'Bieren', position: 1 }] });
		creators(vm);
		prompt.mockReturnValue(' Wijnen ');

		await vm.onSelectorChange('heading', changeEvent(vm, 'headingId', vm.NEW_HEADING));

		expect(vm.createHeading).toHaveBeenCalledWith('Wijnen');
		expect(vm.headingId).toBe(vm.headings[1].id);
		expect(vm.creatingHeading).toBe(false);
	});

	it('restores the previous heading on cancel', async () => {
		const vm = makeVm({ headings: [{ id: 1, name: 'Bieren', position: 1 }, { id: 2, name: 'Wijnen', position: 2 }] });
		creators(vm);
		await vm.onSelectorChange('heading', changeEvent(vm, 'headingId', 2));
		prompt.mockReturnValue(null);

		const event = changeEvent(vm, 'headingId', vm.NEW_HEADING);
		await vm.onSelectorChange('heading', event);

		expect(vm.createHeading).not.toHaveBeenCalled();
		expect(vm.headingId).toBe(2);
		expect(event.target.selectedIndex).toBe(2);
	});

	it('restores the previous heading and shows the message on failure', async () => {
		const vm = makeVm();
		creators(vm, { failOn: ['Bieren'] });
		prompt.mockReturnValue('Bieren');

		await vm.onSelectorChange('heading', changeEvent(vm, 'headingId', vm.NEW_HEADING));

		expect(vm.headingId).toBe(null);
		expect(vm.headingMessage).toBe('Name is taken: Bieren');
	});

	it('works the same for a category', async () => {
		const vm = makeVm();
		creators(vm);
		prompt.mockReturnValue('Bar');

		await vm.onSelectorChange('category', changeEvent(vm, 'categoryId', vm.NEW_CATEGORY));

		expect(vm.createCategory).toHaveBeenCalledWith('Bar');
		expect(vm.categoryId).toBe(vm.categories[0].id);
	});
});
