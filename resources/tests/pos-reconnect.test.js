/**
 * Tests to verify POS reconnect behaviour:
 *
 * When a connect token is present in the URL, the POS app should clear
 * existing auth data so the device can be paired to a new account.
 *
 * Additionally, the Authenticate component should strip the connect token
 * from the URL immediately on mount to prevent accidental sharing.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readFile(relativePath) {
	return readFileSync(resolve(__dirname, '..', relativePath), 'utf-8');
}

describe('POS app reconnect handling', () => {
	const source = readFile('pos/js/app.js');

	it('imports clearAuthData from DeviceAuth', () => {
		expect(source).toContain('clearAuthData');
		expect(source).toMatch(/import\s*\{[^}]*clearAuthData[^}]*\}\s*from/);
	});

	it('checks for connect query parameter before checking auth data', () => {
		const connectCheckPos = source.indexOf("urlParams.has('connect')");
		const authCheckPos = source.indexOf('getAuthData()');

		expect(connectCheckPos).toBeGreaterThan(-1);
		expect(authCheckPos).toBeGreaterThan(-1);
		// The connect parameter check must come before the auth data check
		expect(connectCheckPos).toBeLessThan(authCheckPos);
	});

	it('calls clearAuthData when connect parameter is present', () => {
		// Verify that clearAuthData is called inside the connect parameter check
		const connectBlock = source.substring(
			source.indexOf("urlParams.has('connect')"),
			source.indexOf('getAuthData()')
		);
		expect(connectBlock).toContain('clearAuthData()');
	});
});

describe('Authenticate component URL cleanup', () => {
	const source = readFile('pos/js/views/Authenticate.vue');

	it('reads connect parameter from URL query string', () => {
		expect(source).toContain("urlParams.get('connect')");
	});

	it('removes the query parameter from the URL after reading it', () => {
		// Should use replaceState to remove the connect token from the URL
		expect(source).toContain('window.history.replaceState');

		// The replaceState call should come after reading the connect parameter
		// but before processing it
		const getParamPos = source.indexOf("urlParams.get('connect')");
		const replaceStatePos = source.indexOf('window.history.replaceState');
		const requestTokenPos = source.indexOf('this.requestDeviceToken()');

		expect(replaceStatePos).toBeGreaterThan(getParamPos);
		expect(replaceStatePos).toBeLessThan(requestTokenPos);
	});
});
