/**
 * Source-level tests for the currency picker on the Manage Organisation
 * Settings page.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readFile(relativePath) {
	return readFileSync(resolve(__dirname, '..', relativePath), 'utf-8');
}

describe('Organisation Settings currency picker', () => {
	const source = readFile('manage/js/views/OrganisationSettings.vue');

	it('renders a currency select built from currencyOptions', () => {
		expect(source).toContain("$t('Currency')");
		expect(source).toContain('currencyOptions(');
		expect(source).toContain('<b-form-select id="organisation-currency" v-model="currency" :options="currencyOptions"');
	});

	it('saves through a partial update on the organisation service and updates the running app', () => {
		// PATCH, not PUT: Charon's PUT validates every field (the name is required),
		// so a body carrying only the currency would be rejected.
		expect(source).toContain('this.$organisationService.patch(window.ORGANISATION_ID, { currency: this.currency })');
		expect(source).toContain('setCurrency(this.currency)');
		expect(source).toContain('window.ORGANISATIONS');
	});

	it('explains that past amounts are relabelled', () => {
		expect(source).toContain("$t('Used to label prices, balances and top-ups. Changing it relabels past sales too; no amounts are converted.')");
	});

	it('the shared service offers a patch() that issues PATCH', () => {
		const service = readFile('shared/js/services/AbstractService.js');
		expect(service).toContain('async patch (id, data, parameters)');
		expect(service).toContain("'patch',");
	});
});
