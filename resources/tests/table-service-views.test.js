/**
 * Tests to verify Table Service links and settings in Events views.
 *
 * - POS Events: MUST NOT have standalone "Waiter dashboard" or "Manage tables" links
 *   (table service is now integrated in Headquarters)
 * - Manage Events: MUST have "Manage tables" and "Waiter dashboard" links
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

describe('POS Events.vue table service links (removed - integrated in HQ)', () => {
const template = readVueTemplate('pos');

it('does NOT have Waiter dashboard link (integrated in HQ instead)', () => {
expect(template).not.toContain('Waiter dashboard');
});

it('does NOT have Manage tables link (manage app only)', () => {
expect(template).not.toContain('Manage tables');
});

it('does NOT link to waiter route', () => {
expect(template).not.toContain("name: 'waiter'");
});

it('does NOT link to tables route', () => {
expect(template).not.toContain("name: 'tables'");
});
});

describe('POS Headquarters.vue table service integration', () => {
const content = readVueFile('pos', 'Headquarters.vue');

it('has table service mode flag', () => {
expect(content).toContain('showTableService');
});

it('imports TableService', () => {
expect(content).toContain('TableService');
});

it('imports PatronService', () => {
expect(content).toContain('PatronService');
});

it('imports OrderService', () => {
expect(content).toContain('OrderService');
});

it('imports PaymentPopup component', () => {
expect(content).toContain('PaymentPopup');
});

it('has patron modal', () => {
expect(content).toContain('patronModal');
});

it('has settle balance button', () => {
expect(content).toContain('settleBalance');
});

it('has new order form in patron modal', () => {
expect(content).toContain('submitPatronOrder');
});

it('has order queue with status actions', () => {
expect(content).toContain('markPrepared');
expect(content).toContain('markDelivered');
expect(content).toContain('markVoided');
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

describe('POS Settings.vue table service toggle', () => {
const content = readVueFile('pos', 'Settings.vue');

it('has allowTableService data property', () => {
expect(content).toContain('allowTableService');
});

it('has table service checkbox', () => {
expect(content).toContain('Allow table service at this terminal');
});

it('disables live orders when table service is active', () => {
expect(content).toContain(':disabled="allowTableService"');
});
});
