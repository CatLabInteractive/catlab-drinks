/**
 * Behavioral tests for the POS TableService component: the table grid,
 * the two-step patron modal (selection → details), patron creation,
 * LiveSales wiring, and tab settlement via the payment service.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { globalMountOptions } from './helpers/bootstrapVueStubs';

const serviceMocks = vi.hoisted(() => ({
	tableIndex: vi.fn(),
	patronIndex: vi.fn(),
	patronCreate: vi.fn(),
	patronGet: vi.fn(),
	patronSettle: vi.fn(),
	orderIndex: vi.fn(),
}));

vi.mock('../shared/js/services/TableService', () => ({
	TableService: class {
		constructor(eventId) {
			this.eventId = eventId;
		}
		index(...args) {
			return serviceMocks.tableIndex(...args);
		}
	},
}));

vi.mock('../shared/js/services/PatronService', () => ({
	PatronService: class {
		constructor(eventId) {
			this.eventId = eventId;
		}
		index(...args) {
			return serviceMocks.patronIndex(...args);
		}
		create(...args) {
			return serviceMocks.patronCreate(...args);
		}
		get(...args) {
			return serviceMocks.patronGet(...args);
		}
		settle(...args) {
			return serviceMocks.patronSettle(...args);
		}
	},
}));

vi.mock('../shared/js/services/OrderService', () => ({
	OrderService: class {
		constructor(eventId) {
			this.eventId = eventId;
		}
		index(...args) {
			return serviceMocks.orderIndex(...args);
		}
	},
}));

vi.mock('../shared/js/components/LiveSales.vue', () => ({
	default: {
		name: 'LiveSalesStub',
		props: ['event', 'patronId', 'tableId', 'allowPayLater'],
		emits: ['order-created'],
		template: '<div class="live-sales-stub"></div>',
	},
}));

vi.mock('../shared/js/components/OrderQueue.vue', () => ({
	default: {
		name: 'OrderQueueStub',
		props: ['orderService', 'allowMarkPrepared'],
		methods: { refresh: vi.fn() },
		template: '<div class="order-queue-stub"></div>',
	},
}));

import TableServiceComponent from '../pos/js/components/TableService.vue';

const TABLES = [
	{ id: 1, name: 'Table 1', table_number: 1 },
	{ id: 2, name: 'Table 2', table_number: 2 },
];

const PATRONS = [
	{ id: 11, name: 'Alice', table_id: 1, outstanding_balance: 5, has_unpaid_orders: true },
	{ id: 12, name: null, table_id: null, outstanding_balance: 0, has_unpaid_orders: false },
];

function makeEvent(overrides = {}) {
	return {
		id: 42,
		allow_unpaid_table_orders: true,
		bar_prepares_table_orders: false,
		...overrides,
	};
}

async function mountTableService(options = {}) {
	const paymentService = {
		orders: vi.fn(async () => ({ paymentType: 'cash', discount: 0 })),
		...(options.paymentService || {}),
	};

	const wrapper = mount(TableServiceComponent, {
		props: { event: options.event || makeEvent() },
		global: globalMountOptions({ mocks: { $paymentService: paymentService } }),
	});
	await flushPromises();
	return { wrapper, paymentService };
}

async function clickPatron(wrapper, name) {
	await wrapper
		.find('.modal-stub')
		.findAll('strong')
		.find((el) => el.text() === name)
		.trigger('click');
}

describe('TableService', () => {
	beforeEach(() => {
		vi.clearAllMocks();
		serviceMocks.tableIndex.mockResolvedValue({ items: TABLES });
		serviceMocks.patronIndex.mockResolvedValue({ items: PATRONS });
		serviceMocks.orderIndex.mockResolvedValue({ items: [] });
		serviceMocks.patronGet.mockResolvedValue(PATRONS[0]);
		serviceMocks.patronSettle.mockResolvedValue([]);
	});

	it('renders a card per table plus the no-table option', async () => {
		const { wrapper } = await mountTableService();

		expect(serviceMocks.tableIndex).toHaveBeenCalledWith({ sort: 'table_number' });

		const cards = wrapper.findAll('.table-card');
		expect(cards).toHaveLength(3);
		expect(cards[0].text()).toContain('No Table');
		expect(cards[1].text()).toContain('Table 1');
		expect(cards[2].text()).toContain('Table 2');
	});

	it('opens the patron modal for a table with only its patrons', async () => {
		const { wrapper } = await mountTableService();

		await wrapper.findAll('.table-card')[1].trigger('click');
		await flushPromises();

		const modal = wrapper.find('.modal-stub');
		expect(modal.exists()).toBe(true);
		expect(modal.text()).toContain('Patrons at Table 1');
		expect(modal.text()).toContain('Alice');
		expect(modal.text()).toContain('Unpaid');
		expect(modal.text()).toContain('€5.00');
	});

	it('shows unlinked patrons for the no-table option', async () => {
		const { wrapper } = await mountTableService();

		await wrapper.findAll('.table-card')[0].trigger('click');
		await flushPromises();

		const modal = wrapper.find('.modal-stub');
		expect(modal.text()).toContain('Unlinked Patrons');
		expect(modal.text()).not.toContain('Alice');
		expect(modal.text()).toContain('Patron #12');
	});

	it('moves to patron details with LiveSales wired to the patron', async () => {
		const { wrapper } = await mountTableService();

		await wrapper.findAll('.table-card')[1].trigger('click');
		await flushPromises();

		await clickPatron(wrapper, 'Alice');
		await flushPromises();

		expect(serviceMocks.orderIndex).toHaveBeenCalledWith({ patron_id: 11 });
		expect(wrapper.text()).toContain('Back to patron list');
		expect(wrapper.text()).toContain('Outstanding Balance');

		const liveSales = wrapper.findComponent({ name: 'LiveSalesStub' });
		expect(liveSales.exists()).toBe(true);
		expect(liveSales.props('patronId')).toBe(11);
		expect(liveSales.props('tableId')).toBe(1);
		expect(liveSales.props('allowPayLater')).toBe(true);
	});

	it('returns to the patron list from the details step', async () => {
		const { wrapper } = await mountTableService();

		await wrapper.findAll('.table-card')[1].trigger('click');
		await flushPromises();
		await clickPatron(wrapper, 'Alice');
		await flushPromises();

		const backButton = wrapper
			.findAll('button')
			.find((b) => b.text().includes('Back to patron list'));
		await backButton.trigger('click');

		expect(wrapper.text()).toContain('Patrons at Table 1');
		expect(wrapper.findComponent({ name: 'LiveSalesStub' }).exists()).toBe(false);
	});

	it('creates a patron linked to the selected table and selects it', async () => {
		const newPatron = { id: 13, name: null, table_id: 2, outstanding_balance: 0 };
		serviceMocks.patronCreate.mockResolvedValue(newPatron);

		const { wrapper } = await mountTableService();

		await wrapper.findAll('.table-card')[2].trigger('click');
		await flushPromises();

		const newPatronButton = wrapper
			.findAll('button')
			.find((b) => b.text().includes('New Patron'));
		await newPatronButton.trigger('click');
		await flushPromises();

		expect(serviceMocks.patronCreate).toHaveBeenCalledWith({ name: null, table_id: 2 });
		expect(serviceMocks.orderIndex).toHaveBeenCalledWith({ patron_id: 13 });
		expect(wrapper.text()).toContain('Back to patron list');
	});

	it('creates an unlinked patron from the no-table view', async () => {
		serviceMocks.patronCreate.mockResolvedValue({ id: 14, name: null, table_id: null, outstanding_balance: 0 });

		const { wrapper } = await mountTableService();

		await wrapper.findAll('.table-card')[0].trigger('click');
		await flushPromises();

		await wrapper
			.findAll('button')
			.find((b) => b.text().includes('New Patron'))
			.trigger('click');
		await flushPromises();

		expect(serviceMocks.patronCreate).toHaveBeenCalledWith({ name: null, table_id: null });
	});

	it('settles the outstanding balance through the payment flow', async () => {
		serviceMocks.orderIndex.mockResolvedValue({
			items: [
				{ id: 100, payment_status: 'unpaid', status: 'delivered', price: 5, order: { items: [] } },
			],
		});

		const { wrapper, paymentService } = await mountTableService();

		await wrapper.findAll('.table-card')[1].trigger('click');
		await flushPromises();
		await clickPatron(wrapper, 'Alice');
		await flushPromises();

		await wrapper
			.findAll('button')
			.find((b) => b.text().includes('Pay Outstanding Balance'))
			.trigger('click');
		await flushPromises();

		expect(paymentService.orders).toHaveBeenCalledTimes(1);
		expect(paymentService.orders.mock.calls[0][0].map((o) => o.id)).toEqual([100]);
		expect(serviceMocks.patronSettle).toHaveBeenCalledWith(11, 'cash', 0);
		expect(wrapper.text()).toContain('Payment processed successfully.');
	});

	it('does not settle when the payment is cancelled', async () => {
		serviceMocks.orderIndex.mockResolvedValue({
			items: [
				{ id: 100, payment_status: 'unpaid', status: 'delivered', price: 5, order: { items: [] } },
			],
		});

		const { wrapper } = await mountTableService({
			paymentService: { orders: vi.fn(async () => Promise.reject(new Error('cancelled'))) },
		});

		await wrapper.findAll('.table-card')[1].trigger('click');
		await flushPromises();
		await clickPatron(wrapper, 'Alice');
		await flushPromises();

		await wrapper
			.findAll('button')
			.find((b) => b.text().includes('Pay Outstanding Balance'))
			.trigger('click');
		await flushPromises();

		expect(serviceMocks.patronSettle).not.toHaveBeenCalled();
	});
});
