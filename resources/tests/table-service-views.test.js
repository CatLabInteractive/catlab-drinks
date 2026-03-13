/**
 * Tests to verify Table Service links are correctly placed in Events views.
 *
 * - POS Events.vue: MUST have "Waiter dashboard" link and "Manage tables" link
 * - Manage Events.vue: MUST have "Manage tables" link, "Waiter dashboard" link,
 *   and "allow_unpaid_table_orders" checkbox
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readVueFile(appName, fileName) {
	return readFileSync(
		resolve(__dirname, '..', appName, 'js', 'views', fileName),
		'utf-8'
	);
}

function readVueTemplate(appName) {
	const content = readVueFile(appName, 'Events.vue');
	const startIdx = content.indexOf('<template>');
	const endIdx = content.lastIndexOf('</template>');
	return (startIdx >= 0 && endIdx > startIdx) ? content.substring(startIdx, endIdx + '</template>'.length) : '';
}

describe('POS Events.vue table service links', () => {
	const template = readVueTemplate('pos');

	it('has Waiter dashboard action link', () => {
		expect(template).toContain('Waiter dashboard');
	});

	it('has Manage tables action link', () => {
		expect(template).toContain('Manage tables');
	});

	it('links to waiter route', () => {
		expect(template).toContain("name: 'waiter'");
	});

	it('links to tables route', () => {
		expect(template).toContain("name: 'tables'");
	});
});

describe('Manage Events.vue table service links', () => {
	const template = readVueTemplate('manage');

	it('has Manage tables action link', () => {
		expect(template).toContain('Manage tables');
	});

	it('has Waiter dashboard action link', () => {
		expect(template).toContain('Waiter dashboard');
	});

	it('links to tables route', () => {
		expect(template).toContain("name: 'tables'");
	});

	it('links to waiter route', () => {
		expect(template).toContain("name: 'waiter'");
	});

	it('has Table Service section header', () => {
		expect(template).toContain('Table Service');
	});
});

describe('Manage Events.vue table service settings', () => {
	const content = readVueFile('manage', 'Events.vue');

	it('has allow_unpaid_table_orders checkbox', () => {
		expect(content).toContain('allow_unpaid_table_orders');
	});

	it('has label text for unpaid table orders setting', () => {
		expect(content).toContain('Allow unpaid table orders');
	});
});
