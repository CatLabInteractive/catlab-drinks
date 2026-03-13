/**
* Tests to verify Table Service links and settings in Events views.
*
* - POS Events: MUST NOT have standalone "Waiter dashboard" or "Manage tables" links
*   (table service is now integrated in Headquarters via TableService component)
* - Manage Events: MUST have "Manage tables" and "Waiter dashboard" links
* - POS Headquarters delegates to pos/js/components/TableService.vue
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

function readComponentFile(appName, fileName) {
	return readFileSync(
		resolve(__dirname, '..', appName, 'js', 'components', fileName),
		'utf-8'
	);
}

function readSharedComponentFile(fileName) {
	return readFileSync(
		resolve(__dirname, '..', 'shared', 'js', 'components', fileName),
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

	it('imports TableService component', () => {
		expect(content).toContain('TableService');
	});

	it('imports PaymentPopup component', () => {
		expect(content).toContain('PaymentPopup');
	});

	it('uses table-service component tag', () => {
		expect(content).toContain('<table-service');
	});
});

describe('POS TableService.vue component (pos/js/components/)', () => {
	const content = readComponentFile('pos', 'TableService.vue');

	it('imports TableService API', () => {
		expect(content).toContain('TableService');
	});

	it('imports PatronService', () => {
		expect(content).toContain('PatronService');
	});

	it('imports OrderService', () => {
		expect(content).toContain('OrderService');
	});

	it('reuses LiveSales component for new orders', () => {
		expect(content).toContain('live-sales');
		expect(content).toContain('LiveSales');
	});

	it('passes patron-id prop to LiveSales', () => {
		expect(content).toContain('patron-id');
	});

	it('passes table-id prop to LiveSales', () => {
		expect(content).toContain('table-id');
	});

	it('passes allow-pay-later prop to LiveSales', () => {
		expect(content).toContain('allow-pay-later');
	});

	it('listens for order-created event from LiveSales', () => {
		expect(content).toContain('order-created');
	});

	it('has table modal with patron selection and details', () => {
		expect(content).toContain('tableModal');
	});

	it('has patron selection step', () => {
		expect(content).toContain('selectPatron');
	});

	it('has back to patron list button', () => {
		expect(content).toContain('backToPatronList');
	});

	it('has settle balance button', () => {
		expect(content).toContain('settleBalance');
	});

	it('has order queue with status actions', () => {
		expect(content).toContain('markPrepared');
		expect(content).toContain('markDelivered');
		expect(content).toContain('markVoided');
	});
});

describe('LiveSales.vue table service support', () => {
	const content = readSharedComponentFile('LiveSales.vue');

	it('accepts patronId prop', () => {
		expect(content).toContain('patronId');
	});

	it('accepts tableId prop', () => {
		expect(content).toContain('tableId');
	});

	it('accepts allowPayLater prop', () => {
		expect(content).toContain('allowPayLater');
	});

	it('emits order-created event', () => {
		expect(content).toContain("$emit('order-created'");
	});

	it('sets patron_id on order data when patronId is provided', () => {
		expect(content).toContain('data.patron_id');
	});

	it('hides menu edit button when in patron mode', () => {
		expect(content).toContain('v-if="!patronId"');
	});
});

describe('PaymentPopup.vue pay later support', () => {
	const content = readSharedComponentFile('PaymentPopup.vue');

	it('has pay later button', () => {
		expect(content).toContain('payLater');
	});

	it('shows pay later button conditionally', () => {
		expect(content).toContain('allow_pay_later');
	});

	it('has Pay later label', () => {
		expect(content).toContain('Pay later');
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
