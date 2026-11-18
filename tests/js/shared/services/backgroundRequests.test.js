/**
 * Tests for background requests: requests that a page sends on its own (for
 * example a timed refresh), not because the user clicked something. They must
 * not open an alert() on a server error, must not be answered from the POS
 * offline cache, and must time out, so that a page can tell old data from new.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { AbstractService } from '@resources/shared/js/services/AbstractService';
import { cacheResponse, getCachedResponse, installCacheInterceptors } from '@resources/shared/js/services/ApiCacheService';

const mockStore = {};
vi.mock('localforage', () => ({
	default: {
		getItem: vi.fn((key) => Promise.resolve(mockStore[key] || null)),
		setItem: vi.fn((key, value) => {
			mockStore[key] = value;
			return Promise.resolve(value);
		}),
	},
}));

let requestHandler;

/**
 * Mimics axios: the request config goes to requestHandler, and a rejected
 * request is piped through the error interceptor that the service registers.
 */
function createService() {
	let onError = null;

	const client = (config) => requestHandler(config).then(
		(response) => response,
		(error) => onError(error)
	);
	client.interceptors = {
		response: {
			use: vi.fn((success, error) => {
				onError = error;
			})
		}
	};

	window.axios = { create: vi.fn(() => client) };

	return new AbstractService();
}

function serverError(config) {
	return { message: 'Request failed with status code 500', config, response: { status: 500, data: {} } };
}

function cacheInterceptors() {
	const handlers = {};
	installCacheInterceptors({
		interceptors: {
			response: {
				use: (success, error) => {
					handlers.success = success;
					handlers.error = error;
				}
			}
		}
	}, { markOnline: vi.fn(), markOffline: vi.fn() });
	return handlers;
}

beforeEach(() => {
	Object.keys(mockStore).forEach(k => delete mockStore[k]);
	vi.stubGlobal('CATLAB_DRINKS_CONFIG', { API_PATH: '/api/v1' });
	vi.stubGlobal('alert', vi.fn());
	delete window.OFFLINE_MANAGER;
});

describe('background requests', () => {
	it('background request sets a timeout', async () => {
		const service = createService();
		requestHandler = vi.fn(() => Promise.resolve({ data: {} }));

		await service.execute('get', 'events/1/ordersummary', {}, { background: true });

		expect(requestHandler.mock.calls[0][0]).toMatchObject({ timeout: 20000, background: true });
	});

	it('background request does not alert on 500', async () => {
		const service = createService();
		requestHandler = (config) => Promise.reject(serverError(config));

		await expect(service.execute('get', 'events/1/ordersummary', {}, { background: true })).rejects.toBeTruthy();

		expect(alert).not.toHaveBeenCalled();
	});

	it('normal request still alerts on 500', async () => {
		const service = createService();
		requestHandler = (config) => Promise.reject(serverError(config));

		await expect(service.execute('get', 'events/1/ordersummary')).rejects.toBeTruthy();

		expect(alert).toHaveBeenCalledTimes(1);
	});

	it('background GET is not served from the cache', async () => {
		const { error } = cacheInterceptors();
		await cacheResponse('events/1/ordersummary', { old: true });

		const networkError = {
			config: { method: 'get', url: 'events/1/ordersummary', background: true },
			code: 'ERR_NETWORK',
			message: 'Network Error'
		};

		await expect(error(networkError)).rejects.toBe(networkError);
	});

	it('background GET is not cached', async () => {
		const { success } = cacheInterceptors();

		await success({ data: { fresh: true }, config: { method: 'get', url: 'events/1/ordersummary', background: true } });

		expect(await getCachedResponse('events/1/ordersummary')).toBeNull();
	});
});
