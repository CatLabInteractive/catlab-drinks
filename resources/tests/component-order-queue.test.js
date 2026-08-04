/**
 * Behavioral tests for the shared OrderQueue component: fetching,
 * pending/prepared filtering, the device and prepared-only filters,
 * and the mark prepared/delivered/void actions.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import OrderQueue from '../shared/js/components/OrderQueue.vue';
import { globalMountOptions } from './helpers/bootstrapVueStubs';

function makeOrders() {
	return [
		{ id: 1, status: 'pending', payment_status: 'unpaid', assigned_device_id: 10 },
		{ id: 2, status: 'prepared', payment_status: 'paid', assigned_device_id: 20 },
		{ id: 3, status: 'delivered', payment_status: 'paid', assigned_device_id: 10 },
	];
}

function makeOrderService(orders = makeOrders()) {
	return {
		index: vi.fn(async () => ({ items: orders })),
		markPrepared: vi.fn(async () => {}),
		markDelivered: vi.fn(async () => {}),
		markVoided: vi.fn(async () => {}),
	};
}

async function mountQueue(options = {}) {
	const orderService = options.orderService || makeOrderService();
	const wrapper = mount(OrderQueue, {
		props: {
			orderService,
			...(options.props || {}),
		},
		global: globalMountOptions(),
	});
	await flushPromises();
	return { wrapper, orderService };
}

describe('OrderQueue', () => {
	beforeEach(() => {
		delete window.DEVICE_ID;
	});

	afterEach(() => {
		vi.restoreAllMocks();
	});

	it('fetches pending and prepared orders on mount', async () => {
		const { wrapper, orderService } = await mountQueue();

		expect(orderService.index).toHaveBeenCalledWith({ status: 'pending,prepared' });
		expect(wrapper.find('.spinner-stub').exists()).toBe(false);
		expect(wrapper.findAll('.table-row-stub')).toHaveLength(2);
	});

	it('hides delivered orders from the queue', async () => {
		const { wrapper } = await mountQueue();

		expect(wrapper.vm.filteredOrders.map((o) => o.id)).toEqual([1, 2]);
	});

	it('filters to this device\'s orders via the checkbox', async () => {
		window.DEVICE_ID = 10;
		const { wrapper } = await mountQueue();

		await wrapper.findAll('input[type=checkbox]')[0].setValue(true);

		expect(wrapper.vm.filteredOrders.map((o) => o.id)).toEqual([1]);
	});

	it('filters to prepared orders via the checkbox', async () => {
		const { wrapper } = await mountQueue();

		await wrapper.findAll('input[type=checkbox]')[1].setValue(true);

		expect(wrapper.vm.filteredOrders.map((o) => o.id)).toEqual([2]);
	});

	it('shows the prepared action only for pending orders, and only when allowed', async () => {
		const { wrapper } = await mountQueue();

		const rows = wrapper.findAll('.table-row-stub');
		const labels = (row) => row.findAll('button').map((b) => b.text());

		expect(labels(rows[0])).toEqual(['Prepared', 'Delivered', 'Void']);
		expect(labels(rows[1])).toEqual(['Delivered', 'Void']);

		const { wrapper: noPrepare } = await mountQueue({
			props: { allowMarkPrepared: false },
		});
		expect(labels(noPrepare.findAll('.table-row-stub')[0])).toEqual(['Delivered', 'Void']);
	});

	it('marks an order prepared and refreshes', async () => {
		const { wrapper, orderService } = await mountQueue();

		await wrapper.findAll('.table-row-stub')[0].findAll('button')[0].trigger('click');
		await flushPromises();

		expect(orderService.markPrepared).toHaveBeenCalledWith(1);
		expect(orderService.index).toHaveBeenCalledTimes(2);
	});

	it('marks an order delivered and refreshes', async () => {
		const { wrapper, orderService } = await mountQueue();

		await wrapper.findAll('.table-row-stub')[1].findAll('button')[0].trigger('click');
		await flushPromises();

		expect(orderService.markDelivered).toHaveBeenCalledWith(2);
		expect(orderService.index).toHaveBeenCalledTimes(2);
	});

	it('voids an order only after confirmation', async () => {
		vi.spyOn(window, 'confirm').mockReturnValue(false);
		const { wrapper, orderService } = await mountQueue();

		const voidButton = () => wrapper.findAll('.table-row-stub')[0].findAll('button')[2];

		await voidButton().trigger('click');
		await flushPromises();
		expect(orderService.markVoided).not.toHaveBeenCalled();

		window.confirm.mockReturnValue(true);
		await voidButton().trigger('click');
		await flushPromises();
		expect(orderService.markVoided).toHaveBeenCalledWith(1);
	});
});
