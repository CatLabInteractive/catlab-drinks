/**
* Tests for Table Service frontend services and shared views.
*
* Verifies that:
* - TableService and PatronService extend AbstractService correctly
* - PaymentService has batch orders() method
* - SettingService supports allowTableService setting
* - Shared views (Tables, WaiterDashboard, PatronDetail) exist and have expected structure
*/
import { describe, it, expect } from 'vitest';
import { readFileSync, existsSync } from 'fs';
import { resolve } from 'path';

const sharedPath = resolve(__dirname, '..', 'shared', 'js');

function readFile(path) {
	return readFileSync(path, 'utf-8');
}

describe('TableService', () => {
	const servicePath = resolve(sharedPath, 'services', 'TableService.js');

	it('file exists', () => {
		expect(existsSync(servicePath)).toBe(true);
	});

	it('extends AbstractService', () => {
		const content = readFile(servicePath);
		expect(content).toContain("import {AbstractService} from './AbstractService'");
		expect(content).toContain('extends AbstractService');
	});

	it('sets indexUrl for tables', () => {
		const content = readFile(servicePath);
		expect(content).toContain("'/tables'");
	});

	it('sets entityUrl for tables', () => {
		const content = readFile(servicePath);
		expect(content).toContain("this.entityUrl = 'tables'");
	});

	it('has bulkGenerate method', () => {
		const content = readFile(servicePath);
		expect(content).toContain('bulkGenerate');
	});

	it('does not import unused jQuery', () => {
		const content = readFile(servicePath);
		expect(content).not.toContain("import $ from 'jquery'");
	});
});

describe('PatronService', () => {
	const servicePath = resolve(sharedPath, 'services', 'PatronService.js');

	it('file exists', () => {
		expect(existsSync(servicePath)).toBe(true);
	});

	it('extends AbstractService', () => {
		const content = readFile(servicePath);
		expect(content).toContain("import {AbstractService} from './AbstractService'");
		expect(content).toContain('extends AbstractService');
	});

	it('sets indexUrl for patrons', () => {
		const content = readFile(servicePath);
		expect(content).toContain("'/patrons'");
	});

	it('sets entityUrl for patrons', () => {
		const content = readFile(servicePath);
		expect(content).toContain("this.entityUrl = 'patrons'");
	});

	it('does not import unused jQuery', () => {
		const content = readFile(servicePath);
		expect(content).not.toContain("import $ from 'jquery'");
	});
});

describe('PaymentService batch orders method', () => {
	const servicePath = resolve(sharedPath, 'services', 'PaymentService.js');

	it('file exists', () => {
		expect(existsSync(servicePath)).toBe(true);
	});

	it('has orders() method for batch payment', () => {
		const content = readFile(servicePath);
		expect(content).toContain('async orders(');
	});

	it('orders() method calculates total price', () => {
		const content = readFile(servicePath);
		expect(content).toContain('totalPrice');
		expect(content).toContain('reduce');
	});

	it('orders() method creates combined order', () => {
		const content = readFile(servicePath);
		expect(content).toContain('combinedOrder');
	});

	it('orders() handles no payment method case', () => {
		const content = readFile(servicePath);
		expect(content).toContain('hasPaymentMethod');
	});
});

describe('SettingService table service support', () => {
	const servicePath = resolve(sharedPath, 'services', 'SettingService.js');

	it('has allowTableService default', () => {
		const content = readFile(servicePath);
		expect(content).toContain('allowTableService = false');
	});

	it('loads allowTableService from settings', () => {
		const content = readFile(servicePath);
		expect(content).toContain("settings.allowTableService");
	});

	it('saves allowTableService to settings', () => {
		const content = readFile(servicePath);
		expect(content).toContain('allowTableService: this.allowTableService');
	});
});

describe('Tables.vue shared view', () => {
	const viewPath = resolve(sharedPath, 'views', 'Tables.vue');

	it('file exists', () => {
		expect(existsSync(viewPath)).toBe(true);
	});

	it('imports TableService', () => {
		const content = readFile(viewPath);
		expect(content).toContain('TableService');
	});

	it('has generate tables form', () => {
		const content = readFile(viewPath);
		expect(content).toContain('Generate tables');
		expect(content).toContain('generateTables');
	});

	it('has inline edit support', () => {
		const content = readFile(viewPath);
		expect(content).toContain('startEdit');
		expect(content).toContain('saveEdit');
		expect(content).toContain('cancelEdit');
	});

	it('has delete support', () => {
		const content = readFile(viewPath);
		expect(content).toContain('remove');
	});

	it('displays table_number field', () => {
		const content = readFile(viewPath);
		expect(content).toContain('table_number');
	});
});

describe('WaiterDashboard.vue shared view (still exists for manage app)', () => {
	const viewPath = resolve(sharedPath, 'views', 'WaiterDashboard.vue');

	it('file exists', () => {
		expect(existsSync(viewPath)).toBe(true);
	});

	it('imports required services', () => {
		const content = readFile(viewPath);
		expect(content).toContain('TableService');
		expect(content).toContain('PatronService');
		expect(content).toContain('OrderService');
		expect(content).toContain('EventService');
	});

	it('has Tables tab', () => {
		const content = readFile(viewPath);
		expect(content).toContain('Tables');
	});

	it('has Order Queue tab', () => {
		const content = readFile(viewPath);
		expect(content).toContain('Order Queue');
	});

	it('has No Table option', () => {
		const content = readFile(viewPath);
		expect(content).toContain('No Table');
	});

	it('has New Patron button', () => {
		const content = readFile(viewPath);
		expect(content).toContain('New Patron');
		expect(content).toContain('createPatron');
	});

	it('supports order status updates', () => {
		const content = readFile(viewPath);
		expect(content).toContain('markPrepared');
		expect(content).toContain('markDelivered');
		expect(content).toContain('markVoided');
	});

	it('has filter controls for order queue', () => {
		const content = readFile(viewPath);
		expect(content).toContain('filterMyOrders');
		expect(content).toContain('filterPreparedOnly');
	});
});

describe('PatronDetail.vue shared view (still exists for manage app)', () => {
	const viewPath = resolve(sharedPath, 'views', 'PatronDetail.vue');

	it('file exists', () => {
		expect(existsSync(viewPath)).toBe(true);
	});

	it('imports required services', () => {
		const content = readFile(viewPath);
		expect(content).toContain('PatronService');
		expect(content).toContain('OrderService');
	});

	it('shows outstanding balance', () => {
		const content = readFile(viewPath);
		expect(content).toContain('Outstanding Balance');
		expect(content).toContain('outstanding_balance');
	});

	it('has settle balance functionality', () => {
		const content = readFile(viewPath);
		expect(content).toContain('settleBalance');
		expect(content).toContain('Pay Outstanding Balance');
	});

	it('shows payment status badges', () => {
		const content = readFile(viewPath);
		expect(content).toContain('payment_status');
		expect(content).toContain('paymentStatusVariant');
	});

	it('has back button', () => {
		const content = readFile(viewPath);
		expect(content).toContain('$router.back()');
	});
});
