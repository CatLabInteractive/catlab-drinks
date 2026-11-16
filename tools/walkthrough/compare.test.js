// @vitest-environment node
import { describe, it, expect } from 'vitest';
import { compareReports, normalizeMessage, apiLines } from './compare.mjs';

const item = (id, status = 'pass', consoleErrors = [], note = '') => ({ id, status, consoleErrors, note });

const P2_ERROR = '[pos] Failed to load resource: the server responded with a status of 422 (Unprocessable Content) (/pos-api/v1/events/1/orders)';
const P2_NOTE = '[pos] POST /pos-api/v1/events/1/orders -> 422 {"error":{"status":422}} | sent: {"items":[]}';

function posReport(p2On) {
	// A small report with the shape of the real sequence around the POS block.
	const ids = ['pos-hq', 'pos-sale-cash', 'pos-sale-vouchers', 'pos-settings-sync', 'pos-summary', 'pos-sales-history', 'nfc-card-payment', 'manage-hello'];
	return ids.map((id) => (p2On.includes(id) ? item(id, 'fail', [P2_ERROR], P2_NOTE) : item(id)));
}

describe('compareReports', () => {
	it('finds no differences between identical reports', () => {
		const a = [item('a'), item('b', 'fail', ['[manage] pageerror: boom'], 'error: x')];
		const result = compareReports(a, JSON.parse(JSON.stringify(a)));
		expect(result.differences).toEqual([]);
	});

	it('reports a status change', () => {
		const result = compareReports([item('a', 'pass')], [item('a', 'fail', [], 'error: timeout')]);
		expect(result.differences).toHaveLength(1);
		expect(result.differences[0]).toMatch(/a: status pass -> fail/);
	});

	it('reports a new console error on an item that already fails', () => {
		const base = [item('a', 'fail', ['[manage] pageerror: assign is not a function'])];
		const run = [item('a', 'fail', ['[manage] pageerror: assign is not a function', '[manage] pageerror: something new'])];
		const result = compareReports(base, run);
		expect(result.differences).toHaveLength(1);
		expect(result.differences[0]).toMatch(/added: .*something new/);
	});

	it('reports a removed console error', () => {
		const base = [item('a', 'fail', ['[manage] pageerror: old error'])];
		const run = [item('a', 'fail', [])];
		expect(compareReports(base, run).differences[0]).toMatch(/removed: .*old error/);
	});

	it('reports a new failed API call in the note', () => {
		const base = [item('a', 'fail', ['[x] pageerror: e'])];
		const run = [item('a', 'fail', ['[x] pageerror: e'], '[manage] GET /api/v1/events/7 -> 500 oops')];
		expect(compareReports(base, run).differences[0]).toMatch(/added: .*GET \/api\/v1\/events\/N -> 500/);
	});

	it('treats the development and production variants of the issue 251 error as equal', () => {
		const base = [
			item('box', 'fail', ['[manage] pageerror: assign is not a function']),
			item('select', 'fail', ['[manage] pageerror: el[assignKey] is not a function']),
		];
		const run = [
			item('box', 'fail', ['[manage] pageerror: i is not a function']),
			item('select', 'fail', ['[manage] pageerror: e[Lu] is not a function']),
		];
		expect(compareReports(base, run).differences).toEqual([]);
	});

	it('does not report the 422 when it moves between two items inside the POS block', () => {
		const result = compareReports(posReport(['pos-sale-vouchers', 'nfc-card-payment']), posReport(['pos-sale-cash', 'pos-settings-sync']));
		expect(result.differences).toEqual([]);
	});

	it('reports the 422 on an item outside the POS block', () => {
		const result = compareReports(posReport(['pos-sale-vouchers']), posReport(['pos-sale-vouchers', 'pos-sales-history']));
		expect(result.differences.some((d) => d.startsWith('pos-sales-history:'))).toBe(true);
	});

	it('reports the 422 when it disappears from the POS block', () => {
		const result = compareReports(posReport(['pos-sale-vouchers']), posReport([]));
		expect(result.differences.some((d) => /POS block/.test(d))).toBe(true);
	});

	it('reports an item that is in one file only', () => {
		const result = compareReports([item('a'), item('b')], [item('a'), item('c')]);
		expect(result.differences).toContain('b: only in the baseline');
		expect(result.differences).toContain('c: only in the run');
	});

	it('compares the between-items pseudo-item like any other item', () => {
		const base = [item('a'), item('between-items')];
		const run = [item('a'), item('between-items', 'fail', ['[pos] pageerror: late'])];
		expect(compareReports(base, run).differences[0]).toMatch(/^between-items: status pass -> fail/);
	});
});

describe('normalizeMessage', () => {
	it('removes the host, numeric IDs, run IDs, UUIDs, hashes and file positions', () => {
		const a = normalizeMessage('[manage] x at http://localhost:8098/res/app.js?id=0a1b2c3d4e5f:12:34 for Walk Event 123456 /api/v1/events/12/menu 3f2504e0-4f89-11d3-9a0c-0305e82c3301');
		const b = normalizeMessage('[manage] x at http://127.0.0.1:9000/res/app.js?id=ffeeddccbbaa:99:1 for Walk Event 654321 /api/v1/events/3/menu 9f2504e0-4f89-11d3-9a0c-0305e82c3302');
		expect(a).toBe(b);
	});

	it('keeps the status code', () => {
		expect(normalizeMessage('status of 422 (x)')).not.toBe(normalizeMessage('status of 404 (x)'));
	});
});

describe('apiLines', () => {
	it('keeps method, path and status of each failed API call, without query, answer or body', () => {
		expect(apiLines('dialog alert: x | [pos] POST /pos-api/v1/events/1/orders?x=1 -> 422 {"a":1} | sent: {} | [manage] GET /api/v1/devices/5 -> 404 nope'))
			.toEqual(['[pos] POST /pos-api/v1/events/N/orders -> 422', '[manage] GET /api/v1/devices/N -> 404']);
	});
});
