// @vitest-environment node
import { describe, it, expect } from 'vitest';
import { addressProblems } from './localAddress.mjs';

describe('addressProblems', () => {
	it('accepts local addresses', () => {
		expect(addressProblems({
			WALK_BASE_URL: 'http://localhost:8098',
			WALK_NFC_SIM: 'http://127.0.0.1:3030',
		})).toEqual([]);
		expect(addressProblems({ WALK_BASE_URL: 'http://[::1]:8098' })).toEqual([]);
	});

	it('refuses a public host', () => {
		const problems = addressProblems({ WALK_BASE_URL: 'https://drinks.catlab.eu' });
		expect(problems).toHaveLength(1);
		expect(problems[0]).toMatch(/WALK_BASE_URL/);
		expect(problems[0]).toMatch(/drinks\.catlab\.eu/);
	});

	it('refuses a LAN address', () => {
		expect(addressProblems({ WALK_NFC_SIM: 'http://192.168.1.20:3030' })[0]).toMatch(/WALK_NFC_SIM/);
	});

	it('refuses a value that is not a URL', () => {
		expect(addressProblems({ WALK_BASE_URL: 'not a url' })[0]).toMatch(/WALK_BASE_URL/);
		expect(addressProblems({ WALK_BASE_URL: 'localhost:8098' })).toHaveLength(1);
	});
});
