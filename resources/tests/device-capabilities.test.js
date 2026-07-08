/**
 * Tests for POS device capability gating (allow_sales / allow_topup).
 */
import { describe, it, expect, afterEach } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { getDeviceCapabilities, resolveCapabilityRedirect } from '../pos/js/deviceCapabilities';

const BOTH = { allowSales: true, allowTopup: true };
const SALES_ONLY = { allowSales: true, allowTopup: false };
const TOPUP_ONLY = { allowSales: false, allowTopup: true };
const NONE = { allowSales: false, allowTopup: false };

describe('resolveCapabilityRedirect', () => {

	it('allows everything when both capabilities are enabled', () => {
		['home', 'events', 'hq', 'cards', 'transactions', 'settings'].forEach((name) => {
			expect(resolveCapabilityRedirect(name, BOTH)).toBeNull();
		});
	});

	it('redirects card routes to events on sales-only devices', () => {
		expect(resolveCapabilityRedirect('cards', SALES_ONLY)).toBe('events');
		expect(resolveCapabilityRedirect('transactions', SALES_ONLY)).toBe('events');
		expect(resolveCapabilityRedirect('testTransactions', SALES_ONLY)).toBe('events');
	});

	it('keeps sales routes accessible on sales-only devices', () => {
		expect(resolveCapabilityRedirect('events', SALES_ONLY)).toBeNull();
		expect(resolveCapabilityRedirect('hq', SALES_ONLY)).toBeNull();
	});

	it('redirects sales routes to cards on topup-only devices', () => {
		['home', 'events', 'menu', 'hq', 'sales', 'summary', 'summary-names', 'attendees', 'checkIn'].forEach((name) => {
			expect(resolveCapabilityRedirect(name, TOPUP_ONLY)).toBe('cards');
		});
	});

	it('keeps card routes accessible on topup-only devices', () => {
		expect(resolveCapabilityRedirect('cards', TOPUP_ONLY)).toBeNull();
		expect(resolveCapabilityRedirect('transactions', TOPUP_ONLY)).toBeNull();
	});

	it('redirects everything gated to the disabled screen when nothing is allowed', () => {
		expect(resolveCapabilityRedirect('events', NONE)).toBe('disabled');
		expect(resolveCapabilityRedirect('cards', NONE)).toBe('disabled');
	});

	it('always allows settings and the disabled screen itself', () => {
		expect(resolveCapabilityRedirect('settings', NONE)).toBeNull();
		expect(resolveCapabilityRedirect('disabled', NONE)).toBeNull();
	});

	it('bounces away from the disabled screen when capabilities exist', () => {
		expect(resolveCapabilityRedirect('disabled', BOTH)).toBe('events');
		expect(resolveCapabilityRedirect('disabled', TOPUP_ONLY)).toBe('cards');
		expect(resolveCapabilityRedirect('disabled', SALES_ONLY)).toBe('events');
	});
});

describe('getDeviceCapabilities', () => {

	afterEach(() => {
		delete globalThis.window;
	});

	it('defaults to fully enabled when flags are missing', () => {
		globalThis.window = {};
		expect(getDeviceCapabilities()).toEqual({ allowSales: true, allowTopup: true });
	});

	it('reads explicit false flags', () => {
		globalThis.window = { DEVICE_ALLOW_SALES: false, DEVICE_ALLOW_TOPUP: false };
		expect(getDeviceCapabilities()).toEqual({ allowSales: false, allowTopup: false });
	});
});

describe('POS app wiring', () => {
	const source = readFileSync(resolve(__dirname, '..', 'pos', 'js', 'app.js'), 'utf-8');

	it('sets window capability flags from device data', () => {
		expect(source).toContain('window.DEVICE_ALLOW_SALES');
		expect(source).toContain('window.DEVICE_ALLOW_TOPUP');
	});

	it('registers the capability guard', () => {
		expect(source).toContain('resolveCapabilityRedirect');
		expect(source).toContain('router.beforeEach');
	});

	it('has the disabled route', () => {
		expect(source).toContain("name: 'disabled'");
	});
});
