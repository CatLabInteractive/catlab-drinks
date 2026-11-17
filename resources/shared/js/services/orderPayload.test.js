import { describe, it, expect, vi, beforeEach } from 'vitest';
import { toOrderPayload } from './orderPayload';

describe('toOrderPayload', () => {
	const sale = () => ({
		uid: 'abc',
		location: 'Manual',
		status: 'processed',
		paid: true,
		price: 5,
		discount: 0,
		order: {
			items: [
				{ menuItem: { id: 7, name: 'Cola', price: 2.5 }, amount: 2, price: 2.5 },
			],
		},
	});

	it('sends only the id of each product', () => {
		expect(toOrderPayload(sale()).order.items).toEqual([
			{ menuItem: { id: 7 }, amount: 2, price: 2.5 },
		]);
	});

	it('keeps the other fields of the order', () => {
		const payload = toOrderPayload(sale());
		expect(payload).toMatchObject({
			uid: 'abc', location: 'Manual', status: 'processed', paid: true, price: 5, discount: 0,
		});
	});

	it('does not change the order that the screen and the receipt still use', () => {
		const order = sale();
		toOrderPayload(order);
		expect(order.order.items[0].menuItem).toEqual({ id: 7, name: 'Cola', price: 2.5 });
	});

	it('leaves an item without a product as it is', () => {
		const order = sale();
		order.order.items.push({ amount: 1, price: 1 });
		expect(toOrderPayload(order).order.items[1]).toEqual({ amount: 1, price: 1 });
	});

	it('returns data without order items unchanged', () => {
		expect(toOrderPayload({ uid: 'x' })).toEqual({ uid: 'x' });
		expect(toOrderPayload(null)).toBe(null);
	});
});

describe('OrderService.uploadBatch', () => {
	beforeEach(() => {
		vi.resetModules();
		vi.doMock('localforage', () => ({ removeItem: vi.fn(), iterate: vi.fn(), setItem: vi.fn() }));
		vi.stubGlobal('CATLAB_DRINKS_CONFIG', { API_PATH: '/pos-api/v1' });
		window.axios = { create: vi.fn(() => ({ interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } } })) };
	});

	it('uploads a sale that is already in the queue with only the product id', async () => {
		const { OrderService } = await import('./OrderService');
		const service = new OrderService(3);
		service.execute = vi.fn().mockResolvedValue({});

		await service.uploadBatch([
			{
				localStorageKey: 'event_3_1',
				method: 'create',
				data: { uid: 'abc', order: { items: [{ menuItem: { id: 7, name: 'Cola' }, amount: 1, price: 2.5 }] } },
			},
		]);

		expect(service.execute).toHaveBeenCalledWith('post', '/events/3/orders', {
			items: [
				{ uid: 'abc', order: { items: [{ menuItem: { id: 7 }, amount: 1, price: 2.5 }] } },
			],
		});
	});
});
