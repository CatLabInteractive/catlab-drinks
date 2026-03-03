/**
 * Tests for the ApiCacheService.
 *
 * The ApiCacheService caches successful GET responses using localForage
 * and serves cached responses when the device is offline (network errors).
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { cacheResponse, getCachedResponse, isNetworkError, installCacheInterceptors } from '../shared/js/services/ApiCacheService';

// Mock localForage
const mockStore = {};
vi.mock('localforage', () => ({
	default: {
		getItem: vi.fn((key) => Promise.resolve(mockStore[key] || null)),
		setItem: vi.fn((key, value) => {
			mockStore[key] = value;
			return Promise.resolve(value);
		}),
		removeItem: vi.fn((key) => {
			delete mockStore[key];
			return Promise.resolve();
		}),
	},
}));

beforeEach(() => {
	// Clear mock store
	Object.keys(mockStore).forEach(k => delete mockStore[k]);
});

describe('cacheResponse / getCachedResponse', () => {
	it('should cache and retrieve a response', async () => {
		const data = { items: [{ id: 1, name: 'Test' }] };
		await cacheResponse('/test/url', data);

		const result = await getCachedResponse('/test/url');
		expect(result).toEqual(data);
	});

	it('should return null for uncached URLs', async () => {
		const result = await getCachedResponse('/nonexistent');
		expect(result).toBeNull();
	});

	it('should overwrite existing cache', async () => {
		await cacheResponse('/test/url', { old: true });
		await cacheResponse('/test/url', { new: true });

		const result = await getCachedResponse('/test/url');
		expect(result).toEqual({ new: true });
	});
});

describe('isNetworkError', () => {
	it('should return true for errors without a response', () => {
		const error = { code: 'ERR_NETWORK', message: 'Network Error' };
		expect(isNetworkError(error)).toBe(true);
	});

	it('should return false for errors with a response', () => {
		const error = { response: { status: 500 }, code: 'ERR_BAD_RESPONSE' };
		expect(isNetworkError(error)).toBe(false);
	});

	it('should return true when navigator is offline', () => {
		Object.defineProperty(navigator, 'onLine', { value: false, writable: true, configurable: true });
		const error = { message: 'timeout' };
		expect(isNetworkError(error)).toBe(true);
		Object.defineProperty(navigator, 'onLine', { value: true, writable: true, configurable: true });
	});
});

describe('installCacheInterceptors', () => {
	it('should install response interceptors on an axios instance', () => {
		const axiosInstance = {
			interceptors: {
				response: {
					use: vi.fn()
				}
			}
		};
		const offlineManager = {
			markOnline: vi.fn(),
			markOffline: vi.fn(),
			isOnline: () => true
		};

		installCacheInterceptors(axiosInstance, offlineManager);
		expect(axiosInstance.interceptors.response.use).toHaveBeenCalledTimes(1);
	});

	it('should call markOnline on successful response and cache GET requests', async () => {
		let onSuccess;
		const axiosInstance = {
			interceptors: {
				response: {
					use: vi.fn((success, error) => { onSuccess = success; })
				}
			}
		};
		const offlineManager = {
			markOnline: vi.fn(),
			markOffline: vi.fn(),
			isOnline: () => true
		};

		installCacheInterceptors(axiosInstance, offlineManager);

		const response = {
			data: { id: 1 },
			config: { method: 'get', url: '/test/cache-check' }
		};

		const result = await onSuccess(response);
		expect(offlineManager.markOnline).toHaveBeenCalled();
		expect(result).toBe(response);

		// Verify it was cached
		const cached = await getCachedResponse('/test/cache-check');
		expect(cached).toEqual({ id: 1 });
	});

	it('should call markOffline and return cached data on network error for GET', async () => {
		let onError;
		const axiosInstance = {
			interceptors: {
				response: {
					use: vi.fn((success, error) => { onError = error; })
				}
			}
		};
		const offlineManager = {
			markOnline: vi.fn(),
			markOffline: vi.fn(),
			isOnline: () => false
		};

		installCacheInterceptors(axiosInstance, offlineManager);

		// Pre-populate cache
		await cacheResponse('/test/offline-url', { cached: true });

		const error = {
			config: { method: 'get', url: '/test/offline-url' },
			code: 'ERR_NETWORK',
			message: 'Network Error'
		};

		const result = await onError(error);
		expect(offlineManager.markOffline).toHaveBeenCalled();
		expect(result.data).toEqual({ cached: true });
		expect(result.cached).toBe(true);
	});

	it('should reject on network error for non-GET requests', async () => {
		let onError;
		const axiosInstance = {
			interceptors: {
				response: {
					use: vi.fn((success, error) => { onError = error; })
				}
			}
		};
		const offlineManager = {
			markOnline: vi.fn(),
			markOffline: vi.fn(),
			isOnline: () => false
		};

		installCacheInterceptors(axiosInstance, offlineManager);

		const error = {
			config: { method: 'post', url: '/test/post-url' },
			code: 'ERR_NETWORK',
			message: 'Network Error'
		};

		await expect(onError(error)).rejects.toBe(error);
	});
});
