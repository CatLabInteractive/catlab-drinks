/**
 * Tests for the refresh of the sales summary. The `vue-script-only` plugin in
 * vitest.config.js makes an import of a .vue file give the options of its
 * <script> block. The tests run its data, watchers and methods on a plain
 * object. The template is not involved.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import component from './SalesSummary.vue';

const services = vi.hoisted(() => ({ summary: null, summaryNames: null, event: null, created: 0 }));

vi.mock('../services/OrderService', () => ({
	OrderService: class {
		constructor() {
			services.created++;
		}

		summary(...args) {
			return services.summary(...args);
		}

		summaryNames(...args) {
			return services.summaryNames(...args);
		}

		destroy() {}
	}
}));

vi.mock('../services/MenuService', () => ({
	MenuService: class {
		destroy() {}
	}
}));

vi.mock('../services/EventService', () => ({
	EventService: class {
		get() {
			return services.event();
		}

		destroy() {}
	}
}));

function itemsResponse(amount) {
	return {
		amount,
		totalSales: amount * 3,
		items: { items: [{ menuItem: { id: 1, name: 'Pils' }, amount, totalSales: amount * 3, price: 3 }] }
	};
}

function twoProducts(pils, cola) {
	return {
		amount: pils + cola,
		totalSales: 3 * (pils + cola),
		items: { items: [
			{ menuItem: { id: 1, name: 'Pils' }, amount: pils, totalSales: pils * 3, price: 3 },
			{ menuItem: { id: 2, name: 'Cola' }, amount: cola, totalSales: cola * 3, price: 3 }
		] }
	};
}

function makeVm(summaryType = 'items') {
	const vm = { eventId: null, summaryType };
	Object.assign(vm, component.data.call(vm));
	Object.keys(component.methods).forEach((key) => {
		vm[key] = component.methods[key].bind(vm);
	});
	Object.keys(component.computed || {}).forEach((key) => {
		Object.defineProperty(vm, key, { get: () => component.computed[key].call(vm) });
	});
	vm.$t = (key) => key;
	vm.$ = { appContext: { config: { errorHandler: vi.fn() } } };
	component.mounted.call(vm);
	return vm;
}

async function setEventId(vm, id) {
	vm.eventId = id;
	await component.watch.eventId.call(vm, id);
	await vi.advanceTimersByTimeAsync(0);
}

beforeEach(() => {
	vi.useFakeTimers();
	services.created = 0;
	services.event = vi.fn(() => Promise.resolve({ id: 155, name: 'Quiz' }));
	services.summary = vi.fn(() => Promise.resolve(itemsResponse(2)));
	services.summaryNames = vi.fn(() => Promise.resolve({ amount: 0, totalSales: 0, items: { items: [] } }));
});

afterEach(() => {
	vi.useRealTimers();
});

describe('SalesSummary refresh', () => {
	it('loads as a background request and stores the rows and the load time', async () => {
		const vm = makeVm();

		await setEventId(vm, 155);

		expect(services.summary).toHaveBeenCalledWith({}, { background: true });
		expect(vm.groupedItems[0].sales[0].amount).toBe(2);
		expect(vm.loadedAt).toBe(Date.now());
		expect(vm.firstLoadDone).toBe(true);
		component.unmounted.call(vm);
	});

	it('keeps the rows and the load time when a refresh fails', async () => {
		const vm = makeVm();
		await setEventId(vm, 155);
		const loadedAt = vm.loadedAt;
		const rows = vm.groupedItems;

		services.summary = vi.fn(() => Promise.reject(new Error('offline')));
		await vi.advanceTimersByTimeAsync(30000);

		expect(services.summary).toHaveBeenCalled();
		expect(vm.loadedAt).toBe(loadedAt);
		expect(vm.groupedItems).toBe(rows);
		component.unmounted.call(vm);
	});

	it('refreshes the names tab every 60 seconds', async () => {
		const vm = makeVm('names');
		await setEventId(vm, 155);

		await vi.advanceTimersByTimeAsync(30000);
		expect(services.summaryNames).toHaveBeenCalledTimes(1);

		await vi.advanceTimersByTimeAsync(30000);
		expect(services.summaryNames).toHaveBeenCalledTimes(2);
		component.unmounted.call(vm);
	});

	it('loads the figures even if the event fails to load', async () => {
		services.event = vi.fn(() => Promise.reject(new Error('500')));
		const vm = makeVm();

		await setEventId(vm, 155);

		expect(vm.loadedAt).not.toBeNull();
		component.unmounted.call(vm);
	});

	it('marks the first load as done when it fails, so the spinner stops', async () => {
		services.summary = vi.fn(() => Promise.reject(new Error('offline')));
		const vm = makeVm();

		await setEventId(vm, 155);

		expect(vm.firstLoadDone).toBe(true);
		expect(vm.loadedAt).toBeNull();
		component.unmounted.call(vm);
	});

	it('stops refreshing after unmount', async () => {
		const vm = makeVm();
		await setEventId(vm, 155);

		component.unmounted.call(vm);
		await vi.advanceTimersByTimeAsync(120000);

		expect(services.summary).toHaveBeenCalledTimes(1);
	});

	it('does not start refreshing when the event id arrives after unmount', async () => {
		const vm = makeVm();
		component.unmounted.call(vm);

		await setEventId(vm, 155);
		await vi.advanceTimersByTimeAsync(120000);

		expect(services.summary).not.toHaveBeenCalled();
	});

	it('reports an error that is not a failed request to the app error handler', async () => {
		services.summary = vi.fn(() => Promise.resolve({ items: null }));
		const vm = makeVm();

		await setEventId(vm, 155);

		expect(vm.$.appContext.config.errorHandler).toHaveBeenCalledTimes(1);
		expect(vm.$.appContext.config.errorHandler.mock.calls[0][0]).toBeInstanceOf(TypeError);
		component.unmounted.call(vm);
	});

	it('does not report a failed request', async () => {
		services.summary = vi.fn(() => Promise.reject(Object.assign(new Error('timeout'), { isAxiosError: true })));
		const vm = makeVm();

		await setEventId(vm, 155);

		expect(vm.$.appContext.config.errorHandler).not.toHaveBeenCalled();
		component.unmounted.call(vm);
	});

	it('sorts the rows when a header is clicked', async () => {
		services.summary = vi.fn(() => Promise.resolve(twoProducts(2, 5)));
		const vm = makeVm();
		await setEventId(vm, 155);

		vm.setSort('amount');

		expect(vm.sortedGroups.map(g => g.menuItem.name)).toEqual(['Cola', 'Pils']);
		component.unmounted.call(vm);
	});

	it('keeps the sort after a refresh', async () => {
		services.summary = vi.fn(() => Promise.resolve(twoProducts(2, 5)));
		const vm = makeVm();
		await setEventId(vm, 155);
		vm.setSort('amount');

		services.summary = vi.fn(() => Promise.resolve(twoProducts(9, 5)));
		await vi.advanceTimersByTimeAsync(30000);

		expect(vm.sortedGroups.map(g => g.menuItem.name)).toEqual(['Pils', 'Cola']);
		component.unmounted.call(vm);
	});
});
