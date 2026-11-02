/**
 * Source-level tests for the organisation currency:
 *
 * 1. Each app sets the shared formatter's currency at boot from where it
 *    learns about the organisation (Manage: /users/me, POS: the device
 *    payload, guest page: the EVENT_CURRENCY global) and exposes
 *    $filters.money to its templates.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readFile(relativePath) {
	return readFileSync(resolve(__dirname, '..', relativePath), 'utf-8');
}

describe('currency wiring at boot', () => {
	it('Manage sets the currency from the selected organisation', () => {
		const source = readFile('manage/js/app.js');
		expect(source).toContain("import { setCurrency, formatMoney } from '../../shared/js/utils/money';");
		expect(source).toContain('setCurrency(organisation ? organisation.currency : null);');
		expect(source).toContain('money: formatMoney');
	});

	it('POS sets the currency from the device payload', () => {
		const source = readFile('pos/js/app.js');
		expect(source).toContain("import { setCurrency, formatMoney } from '../../shared/js/utils/money';");
		expect(source).toContain('setCurrency(deviceData.organisation.currency);');
		expect(source).toContain('money: formatMoney');
	});

	it('the guest app sets the currency from the page global', () => {
		const source = readFile('clients/js/app.js');
		expect(source).toContain("import { setCurrency, formatMoney } from '../../shared/js/utils/money';");
		expect(source).toContain("if (typeof(EVENT_CURRENCY) !== 'undefined') {");
		expect(source).toContain('setCurrency(EVENT_CURRENCY);');
		expect(source).toContain('money: formatMoney');
	});
});
