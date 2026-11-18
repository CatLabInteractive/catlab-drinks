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

function makeVm(summaryType = 'items') {
	const vm = { eventId: null, summaryType };
	Object.assign(vm, component.data.call(vm));
	Object.keys(component.methods).forEach((key) => {
		vm[key] = component.methods[key].bind(vm);
	});
	vm.$t = (key) => key;
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
});
