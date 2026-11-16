// Walk-through items for the shared views in Manage (sales, transactions, cards) and the device
// actions in Manage that need a paired device (edit, license, signed cards, delete).
import { note, goto, text, modal, noModal, rowAction, assert } from './lib.mjs';

async function editDevice(s, { toggleSales = 0, toggleTopup = 0 } = {}) {
	const p = s.manage;
	await goto(p, '/manage/devices');
	await rowAction(p, s.deviceName, 'Edit');
	const m = await modal(p, 'Edit device');
	const sales = m.locator('.custom-control').nth(0).locator('label');
	const topup = m.locator('.custom-control').nth(1).locator('label');
	for (let i = 0; i < toggleSales; i++) await sales.click();
	for (let i = 0; i < toggleTopup; i++) await topup.click();
	const state = await m.locator('.custom-control input[type=checkbox]').evaluateAll((els) => els.map((e) => e.checked));
	await m.locator('.modal-footer button', { hasText: 'Save' }).click();
	await noModal(p);
	return state;
}

export function sharedItems(s) {
	return {
		'manage-summary': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/summary`);
			await p.locator('.order-summary table tbody tr').first().waitFor();
			await p.locator('.order-summary table', { hasText: 'Walk Beer' }).waitFor();
			return p;
		},
		'manage-summary-names': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/summary`);
			await p.locator('.nav-tabs a', { hasText: 'Orderer names' }).click();
			await p.waitForURL(/summary-names/);
			await p.locator('.order-summary table').waitFor();
			await p.screenshot({ path: s.out('manage-summary-names-tab.png') });
			await p.locator('.nav-tabs a', { hasText: 'Sold items' }).click();
			await p.waitForURL(/summary$/);
			await p.locator('.order-summary table').waitFor();
			return p;
		},
		'manage-summary-detail-modal': async () => {
			const p = s.manage;
			// Only the "Orderer names" tab has buttons that open the details.
			await goto(p, `/manage/events/${s.eventId}/summary-names`);
			await p.locator('.order-summary table button').first().click();
			const m = await modal(p, 'Order details');
			await m.locator('.order').first().waitFor();
			await p.screenshot({ path: s.out('manage-summary-detail-modal-open.png') });
			await m.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'manage-sales-history': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/sales`);
			await p.locator('.order-history .order').first().waitFor();
			note('orders listed: ' + (await p.locator('.order-history .order').count()));
			return p;
		},
		'manage-transactions': async () => {
			const p = s.manage;
			await goto(p, '/manage/transactions');
			await p.locator('table a.card-id').first().waitFor();
			await p.locator('table a.card-id').first().click();
			const cm = await modal(p, 'Card details');
			await cm.locator('table').first().waitFor();
			await p.screenshot({ path: s.out('manage-transactions-card-modal.png') });
			await cm.locator('.modal-footer button').last().click();
			await noModal(p);
			const orders = p.locator('table a.btn-info', { hasText: 'Order #' });
			assert(await orders.count() > 0, 'no transaction is linked to an order (no "Order #" button)');
			await orders.first().click();
			const om = await modal(p, 'Order details');
			await om.locator('table').first().waitFor();
			await p.screenshot({ path: s.out('manage-transactions-order-modal.png') });
			await om.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'manage-card-details-topup': async () => {
			const p = s.manage;
			await goto(p, '/manage/transactions');
			await p.locator('table a.card-id').first().click();
			const cm = await modal(p, 'Card details');
			await cm.locator('#amount').waitFor();
			const before = await text(p, '.modal.show table tr:has(td:text-is("Balance")) td:nth-child(2)');
			await cm.locator('#amount').fill('3');
			await cm.locator('#reason').fill('Walk-through online topup');
			await cm.locator('button.btn-success', { hasText: 'Topup' }).click();
			await cm.locator('button', { hasText: 'Confirm' }).click();
			await cm.locator('.alert-success', { hasText: 'Topup successful' }).waitFor({ timeout: 15000 });
			await p.waitForTimeout(1000);
			const after = await text(p, '.modal.show table tr:has(td:text-is("Balance")) td:nth-child(2)');
			note(`balance in modal: ${before} -> ${after}`);
			await p.screenshot({ path: s.out('manage-card-details-topup.png') });
			await cm.locator('.modal-footer button').last().click();
			await noModal(p);
			return p;
		},
		'manage-financial-overview': async () => {
			const p = s.manage;
			await goto(p, '/manage/financial-overview');
			await p.locator('table.table', { hasText: 'Total credit' }).waitFor();
			return p;
		},
		'manage-cards': async () => {
			const p = s.manage;
			await goto(p, '/manage/cards');
			await p.locator('h1').first().waitFor();
			note('page text: ' + (await text(p, '.container-fluid')).slice(0, 120));
			return p;
		},
		'manage-test-transactions': async () => {
			const p = s.manage;
			await goto(p, '/manage/tests/transactions');
			await p.locator('.container-fluid').first().waitFor();
			return p;
		},
		'manage-hello': async () => {
			const p = s.manage;
			await goto(p, '/manage/hello');
			await p.locator('h1', { hasText: 'Hello world' }).waitFor();
			return p;
		},
		'manage-devices-signed-cards': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			const row = p.locator('tr', { hasText: s.deviceName });
			await row.locator('a', { hasText: /^\s*\d+\s*$/ }).first().click();
			const m = p.locator('.modal.show').last();
			await m.locator('table a').first().waitFor();
			await p.waitForTimeout(500);
			await p.screenshot({ path: s.out('manage-devices-signed-cards-list.png') });
			await m.locator('table a').first().click();
			const cm = await modal(p, 'Card details');
			await cm.locator('table').first().waitFor();
			await cm.locator('.modal-footer button').click();
			await p.waitForTimeout(500);
			await p.locator('.modal.show .modal-footer button').last().click();
			await noModal(p);
			return p;
		},
		'manage-public-keys-list': async () => {
			const p = s.manage;
			await goto(p, '/manage/public-keys');
			const row = p.locator('tr', { hasText: s.deviceName });
			await row.locator('.badge-success', { hasText: 'Approved' }).waitFor();
			await row.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Revoke' }).waitFor();
			await p.screenshot({ path: s.out('manage-public-keys-list-dropdown.png') });
			await p.keyboard.press('Escape');
			return p;
		},
		'manage-devices-license': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await rowAction(p, s.deviceName, 'Enter License');
			const m = await modal(p, 'Enter license');
			await m.locator('#license-key-input').fill('not-a-valid-license');
			await m.locator('.modal-footer button', { hasText: 'Apply License' }).click();
			await m.locator('.alert-danger').waitFor({ timeout: 15000 });
			note('license error: ' + (await text(p, '.modal.show .alert-danger')));
			await p.screenshot({ path: s.out('manage-devices-license-error.png') });
			await m.locator('.modal-footer button', { hasText: 'Cancel' }).click();
			await noModal(p);
			return p;
		},
		'manage-devices-buy-license-link': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await p.locator('tr', { hasText: s.deviceName }).locator('.dropdown-toggle').click();
			const href = await p.locator('.dropdown-menu.show a', { hasText: 'Buy License' }).getAttribute('href');
			assert(href, 'Buy License has no link');
			note('Buy License link (not opened): ' + href.split('?')[0]);
			await p.keyboard.press('Escape');
			return p;
		},
		'manage-devices-edit': async () => {
			const p = s.manage;
			// Each checkbox on, off, and on again: the device keeps both capabilities.
			const state = await editDevice(s, { toggleSales: 2, toggleTopup: 2 });
			assert(state.every(Boolean), 'capabilities changed: ' + state.join(','));
			await p.locator('tr', { hasText: s.deviceName }).locator('.badge', { hasText: 'Topup' }).waitFor();
			return p;
		},
		'pos-disabled-device': async () => {
			const state = await editDevice(s, { toggleSales: 1, toggleTopup: 1 });
			assert(state.every((x) => !x), 'expected both capabilities off: ' + state.join(','));
			await s.manage.locator('tr', { hasText: s.deviceName }).locator('.badge', { hasText: 'Disabled' }).waitFor();
			const pos = s.pos;
			await goto(pos, '/pos/events');
			await pos.waitForURL(/\/disabled/, { timeout: 15000 });
			await pos.locator('.alert-warning', { hasText: 'no functions enabled' }).waitFor();
			await pos.screenshot({ path: s.out('pos-disabled-device.png'), fullPage: true });
			const back = await editDevice(s, { toggleSales: 1, toggleTopup: 1 });
			assert(back.every(Boolean), 'capabilities not restored');
			await goto(pos, '/pos/events');
			await pos.locator('tr', { hasText: s.eventName }).waitFor({ timeout: 15000 });
			return pos;
		},
		'manage-devices-delete': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await rowAction(p, s.deviceName, 'Delete');
			await p.waitForFunction((n) => {
				const tr = [...document.querySelectorAll('tr')].find((t) => t.textContent.includes(n));
				return !tr || tr.textContent.includes('Deleted');
			}, s.deviceName, { timeout: 15000 });
			return p;
		},
	};
}
