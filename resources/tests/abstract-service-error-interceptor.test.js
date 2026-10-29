/**
 * Tests for the AbstractService axios error interceptor.
 *
 * The interceptor handles 401 (reload), 403 (redirect) and 422 (alert) itself.
 * It must not resolve the request promise with a bogus value afterwards:
 * that makes execute() resolve `undefined` and callers like index() crash
 * with "Cannot read properties of undefined (reading 'items')".
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { AbstractService, getApiErrorMessage } from '../shared/js/services/AbstractService';

let requestHandler;
let reloadMock;
let locationSetter;

/**
 * Mimics axios semantics: a rejected request is piped through the
 * registered error interceptor; whatever the interceptor returns
 * becomes the result of the request promise.
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

	const service = new AbstractService();
	service.entityUrl = 'devices';
	service.indexUrl = 'organisations/1/devices';
	return service;
}

function httpError(status, data = {}) {
	return {
		message: 'Request failed with status code ' + status,
		response: { status, data }
	};
}

/**
 * Resolves 'pending' if the given promise does not settle within a few ticks.
 */
function settlementOf(promise) {
	return Promise.race([
		promise.then(() => 'resolved', () => 'rejected'),
		new Promise((resolve) => setTimeout(() => resolve('pending'), 25))
	]);
}

beforeEach(() => {
	vi.stubGlobal('CATLAB_DRINKS_CONFIG', { API_PATH: '/api/v1' });
	vi.stubGlobal('alert', vi.fn());
	delete window.OFFLINE_MANAGER;

	reloadMock = vi.fn();
	locationSetter = vi.fn();
	Object.defineProperty(window, 'location', {
		configurable: true,
		get: () => ({ reload: reloadMock, origin: 'https://drinks.catlab.eu' }),
		set: locationSetter
	});
});

describe('AbstractService error interceptor', () => {
	it('reloads and leaves the request pending on 401, so index() never reads items of undefined', async () => {
		const service = createService();
		requestHandler = () => Promise.reject(httpError(401));

		expect(await settlementOf(service.index())).toBe('pending');
		expect(reloadMock).toHaveBeenCalled();
	});

	it('redirects and leaves the request pending on 403', async () => {
		const service = createService();
		requestHandler = () => Promise.reject(httpError(403));

		expect(await settlementOf(service.index())).toBe('pending');
		expect(locationSetter).toHaveBeenCalledWith('https://drinks.catlab.eu');
	});

	it('alerts the issues and rejects on 422', async () => {
		const service = createService();
		const error = httpError(422, {
			error: {
				message: 'Could not save',
				issues: { name: ['Name is required'] }
			}
		});
		requestHandler = () => Promise.reject(error);

		await expect(service.update(1, { name: '' })).rejects.toBe(error);
		expect(window.alert).toHaveBeenCalledWith('Could not save: \nName is required');
	});

	it('alerts the details and rejects on a JSON:API style 422 (Charon error handler)', async () => {
		const service = createService();
		const error = httpError(422, {
			errors: [
				{ status: 422, title: 'Resource validation failed.', detail: 'Invalid license key: not valid base64.' }
			]
		});
		requestHandler = () => Promise.reject(error);

		await expect(service.update(1, { name: '' })).rejects.toBe(error);
		expect(window.alert).toHaveBeenCalledWith('Resource validation failed.: \nInvalid license key: not valid base64.');
	});

	it('alerts the message and rejects on a Laravel style 422', async () => {
		const service = createService();
		const error = httpError(422, { message: 'This organisation\'s name is managed on CatLab Accounts.' });
		requestHandler = () => Promise.reject(error);

		await expect(service.update(1, { name: '' })).rejects.toBe(error);
		expect(window.alert).toHaveBeenCalledWith('This organisation\'s name is managed on CatLab Accounts.');
	});

	it('still rejects (instead of crashing) on a 422 without a body', async () => {
		const service = createService();
		const error = httpError(422, '');
		requestHandler = () => Promise.reject(error);

		await expect(service.update(1, { name: '' })).rejects.toBe(error);
		expect(window.alert).toHaveBeenCalledWith('Request failed with status code 422');
	});
});

describe('getApiErrorMessage', () => {
	it('reads the CatLab error format', () => {
		expect(getApiErrorMessage(httpError(422, { error: { message: 'Device has no public key to approve.' } })))
			.toBe('Device has no public key to approve.');
	});

	it('reads the JSON:API error format', () => {
		expect(getApiErrorMessage(httpError(422, { errors: [{ title: 'Resource validation failed.', detail: 'Invalid license key: license has expired.' }] })))
			.toBe('Invalid license key: license has expired.');
	});

	it('returns null when the response has no recognisable message', () => {
		expect(getApiErrorMessage(httpError(500, '<html></html>'))).toBe(null);
		expect(getApiErrorMessage(new Error('Network Error'))).toBe(null);
	});
});
