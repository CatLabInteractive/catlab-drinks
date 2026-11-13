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
 * Tests for the order handlers of the POS sales screen when the tiles are
 * grouped by heading. The `vue-script-only` plugin in vitest.config.js makes
 * an import of a .vue file give the options of its <script> block. The test
 * runs its data, computed values and methods on a plain object whose `items`
 * is reactive, as in the component. The template is not involved.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reactive } from 'vue';
import component from './LiveSales.vue';

vi.mock('../services/MenuService', () => ({
	MenuService: class {
		index() {
			return Promise.resolve({ items: [] });
		}

		destroy() {}
	}
}));

vi.mock('../services/OrderService', () => ({
	OrderService: class {
		startPeriodicUpload() {}

		destroy() {}
	}
}));

const BIER = { id: 10, name: 'Bieren', position: 1 };
// The same position as BIER: the id decides, so FRIS comes after BIER.
const FRIS = { id: 11, name: 'Frisdrank', position: 1 };

/**
 * A: no heading key at all (as Charon leaves it out), B and D under BIER
 * (D is the second product of that group), C under FRIS, E with heading null.
 */
function menuItems() {
	return [
		{ id: 1, name: 'A', price: 1.5, amount: 0 },
		{ id: 2, name: 'B', price: 2.5, amount: 0, heading: BIER },
		{ id: 3, name: 'C', price: 2, amount: 0, heading: FRIS },
		{ id: 4, name: 'D', price: 3, amount: 0, heading: BIER },
		{ id: 5, name: 'E', price: 4, amount: 0, heading: null }
	];
}

function makeVm() {
	const vm = {
		event: { id: 7 }
	};
	Object.assign(vm, component.data.call(vm));
	vm.items = reactive(menuItems());
	Object.keys(component.computed).forEach((key) => {
		Object.defineProperty(vm, key, { get: () => component.computed[key].call(vm) });
	});
	Object.keys(component.methods).forEach((key) => {
		vm[key] = component.methods[key].bind(vm);
	});
	vm.$t = (key) => key;
	vm.$refs = {
		confirmModal: { show: vi.fn(), hide: vi.fn() },
		processedModal: { show: vi.fn(), hide: vi.fn() },
		declinedModal: { show: vi.fn(), hide: vi.fn() }
	};
	vm.orderService = {
		prepare: vi.fn(async (data) => ({ ...data })),
		create: vi.fn(async (order) => order)
	};
	vm.$paymentService = { order: vi.fn(async () => ({ paymentType: 'cash' })) };
	vm.updateTotals();
	return vm;
}

function tile(vm, name) {
	for (const group of vm.groups) {
		const item = group.items.find(candidate => candidate.name === name);
		if (item) {
			return item;
		}
	}
	throw new Error('No tile ' + name);
}

function amounts(vm) {
	return vm.items.map(item => [item.name, item.amount]);
}

function clickEvent() {
	return { preventDefault: vi.fn(), stopPropagation: vi.fn() };
}

beforeEach(() => {
	vi.useFakeTimers();
});

describe('LiveSales tiles grouped by heading', () => {
	it('groups loose products first, then the headings in position and id order', () => {
		const vm = makeVm();
		expect(vm.groups.map(group => [group.heading ? group.heading.name : null, group.items.map(item => item.name)]))
			.toEqual([[null, ['A', 'E']], ['Bieren', ['B', 'D']], ['Frisdrank', ['C']]]);
	});

	it('changes only the ordered product of the flat list, also when it is not first in its group', async () => {
		const vm = makeVm();
		const d = tile(vm, 'D');
		const event = clickEvent();

		await vm.increaseOrder(d, event);
		await vm.increaseOrder(tile(vm, 'D'));

		expect(event.preventDefault).toHaveBeenCalled();
		expect(event.stopPropagation).toHaveBeenCalled();
		expect(amounts(vm)).toEqual([['A', 0], ['B', 0], ['C', 0], ['D', 2], ['E', 0]]);
		expect(vm.items.length).toBe(5);
		expect(vm.items[3]).toBe(d);
		expect(vm.totals).toEqual({ items: 0, price: 6, amount: 2 });
	});

	it('handles a product without a heading key, a product with heading null, and the second heading of the same position', async () => {
		const vm = makeVm();

		await vm.increaseOrder(tile(vm, 'A'));
		await vm.increaseOrder(tile(vm, 'E'));
		await vm.increaseOrder(tile(vm, 'C'));
		await vm.increaseOrder(tile(vm, 'C'));

		expect(amounts(vm)).toEqual([['A', 1], ['B', 0], ['C', 2], ['D', 0], ['E', 1]]);
		expect(vm.totals.amount).toBe(4);
		expect(vm.totals.price).toBeCloseTo(1.5 + 4 + 2 * 2);
	});

	it('decreases only the product itself and never below 0', async () => {
		const vm = makeVm();
		await vm.increaseOrder(tile(vm, 'D'));
		await vm.increaseOrder(tile(vm, 'D'));
		await vm.increaseOrder(tile(vm, 'B'));

		await vm.decreaseOrder(tile(vm, 'D'), clickEvent());
		await vm.decreaseOrder(tile(vm, 'A'), clickEvent());

		expect(amounts(vm)).toEqual([['A', 0], ['B', 1], ['C', 0], ['D', 1], ['E', 0]]);
		expect(vm.totals.amount).toBe(2);
		expect(vm.totals.price).toBeCloseTo(2.5 + 3);
	});

	it('submits and confirms exactly the ordered products', async () => {
		const vm = makeVm();
		await vm.increaseOrder(tile(vm, 'D'));
		await vm.increaseOrder(tile(vm, 'C'));
		await vm.increaseOrder(tile(vm, 'D'));
		await vm.decreaseOrder(tile(vm, 'A'));

		await vm.submit();

		const expected = [
			{ menuItem: { id: 3, name: 'C' }, amount: 1, price: 2 },
			{ menuItem: { id: 4, name: 'D' }, amount: 2, price: 3 }
		];
		expect(vm.selectedItems).toEqual(expected);
		expect(vm.$refs.confirmModal.show).toHaveBeenCalledTimes(1);

		await vm.confirm();

		expect(vm.orderService.prepare).toHaveBeenCalledTimes(1);
		const data = vm.orderService.prepare.mock.calls[0][0];
		expect(data.order.items).toEqual(expected);
		expect(data.price).toBe(8);
		expect(vm.orderService.create).toHaveBeenCalledTimes(1);
		expect(vm.orderService.create.mock.calls[0][0].order.items).toEqual(expected);
		expect(vm.orderService.create.mock.calls[0][0].status).toBe('processed');
		// After the order, the screen starts again from zero.
		expect(amounts(vm).every(([, amount]) => amount === 0)).toBe(true);
		expect(vm.totals.amount).toBe(0);
	});

	it('does not open the confirm step without ordered products', async () => {
		const vm = makeVm();
		await vm.decreaseOrder(tile(vm, 'B'));

		await vm.submit();

		expect(vm.$refs.confirmModal.show).not.toHaveBeenCalled();
		expect(vm.selectedItems).toEqual([]);
	});
});
