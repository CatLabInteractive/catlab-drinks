// Walk-through items for device pairing and the POS app (/pos/), without cards.
import { BASE, RUN, dialogs, note, expectConsoleError, newPage, goto, text, modal, noModal, rowAction, assert, localUrl } from './lib.mjs';

async function addToOrder(p, name, times = 1) {
	for (let i = 0; i < times; i++) await p.locator('.live-orders .product', { hasText: name }).locator('.name').click();
}

export async function hq(s) {
	const p = s.pos;
	await goto(p, `/pos/events/${s.eventId}/hq`);
	await p.locator('.live-orders .product').first().waitFor();
	return p;
}

/** Fill the live order, confirm it, and wait for the payment popup. */
export async function startSale(p, products) {
	for (const [name, times] of products) await addToOrder(p, name, times);
	await p.locator('#live-orders button.btn-lg', { hasText: 'Confirm' }).click();
	const m = await modal(p, 'Confirm order');
	await m.locator('.modal-footer .btn-primary').click();
	return modal(p, 'Payment');
}

export function posItems(s) {
	return {
		'manage-devices-pair': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await p.click('button[title="Pair a device"]');
			const m = await modal(p, 'Connect & authenticate a device');
			const connectUrl = await m.locator('#connect-url').inputValue();
			note('connect URL host in Manage (not opened): ' + new URL(connectUrl).host);
			s.pos = await newPage(s.browser, 'pos');
			const pos = s.pos;
			// Never open the public host in the connect URL. Open the same path on the local base.
			await pos.goto(localUrl(connectUrl));
			await pos.screenshot({ path: s.out('blade-connect.png'), fullPage: true });
			await pos.click('a.btn-browser');
			await pos.waitForSelector('.pairing-code-display', { timeout: 20000 });
			const code = (await pos.textContent('.pairing-code-display')).trim();
			await pos.screenshot({ path: s.out('pos-pairing-code.png'), fullPage: true });
			await p.locator('#pairing-code').waitFor({ timeout: 20000 });
			await p.fill('#pairing-code', code);
			s.deviceName = 'Walk POS ' + RUN;
			await p.fill('#device-name', s.deviceName);
			await p.screenshot({ path: s.out('manage-devices-pair-code.png') });
			await p.locator('.modal.show button', { hasText: 'Pair Device' }).click();
			await pos.locator('nav.navbar').waitFor({ timeout: 30000 });
			await noModal(p, 20000);
			await p.locator('tr', { hasText: s.deviceName }).waitFor();
			return pos;
		},
		'pos-events': async () => {
			const p = s.pos;
			await goto(p, '/pos/events');
			await p.locator('tr', { hasText: s.eventName }).waitFor();
			await p.locator('tr', { hasText: s.eventName }).locator('.dropdown-toggle').click();
			for (const label of ['Sales overview', 'Order history', 'Check-In']) {
				await p.locator('.dropdown-menu.show a', { hasText: label }).first().waitFor();
			}
			await p.screenshot({ path: s.out('pos-events-dropdown.png') });
			await p.keyboard.press('Escape');
			const row = p.locator('tr', { hasText: s.eventName });
			await row.locator('button', { hasText: /^\s*Open\s*$/ }).click();
			await row.locator('button', { hasText: /^\s*Closed\s*$/ }).waitFor();
			await row.locator('button', { hasText: /^\s*Closed\s*$/ }).click();
			await row.locator('button', { hasText: /^\s*Open\s*$/ }).waitFor();
			await row.locator('a', { hasText: s.eventName }).click();
			await p.waitForURL(/\/hq/);
			return p;
		},
		'pos-language-toggle': async () => {
			const p = s.pos;
			await goto(p, '/pos/events');
			const dd = p.locator('.navbar .nav-item.dropdown').last();
			await dd.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Nederlands' }).click();
			await p.locator('.navbar a', { hasText: 'Evenementen' }).waitFor();
			await dd.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'English' }).click();
			await p.locator('.navbar a', { hasText: 'Events' }).waitFor();
			return p;
		},
		'pos-menu': async () => {
			const p = s.pos;
			await goto(p, `/pos/events/${s.eventId}/menu`);
			const row = p.locator('tr', { hasText: 'Walk Tea' });
			await row.waitFor();
			// The selling button switches between green and red. Switch it twice.
			for (const cls of ['btn-danger', 'btn-success']) {
				await row.locator('td button.btn-success, td button.btn-danger').first().click();
				await row.locator('td button.' + cls).waitFor();
			}
			return p;
		},
		'pos-hq': async () => {
			const p = await hq(s);
			await p.locator('h3.live-orders-heading', { hasText: 'Drinks' }).waitFor();
			await p.locator('.remote-orders').waitFor();
			return p;
		},
		'pos-sale-cash': async () => {
			const p = await hq(s);
			await addToOrder(p, 'Walk Cola', 2);
			await p.locator('.live-orders .product', { hasText: 'Walk Cola' }).locator('button.btn-danger').click();
			const amount = await text(p, '.live-orders .product:has-text("Walk Cola") .amount');
			assert(amount === '1', 'minus button: expected 1, got ' + amount);
			const pay = await startSale(p, [['Walk Beer', 2]]);
			await p.screenshot({ path: s.out('pos-sale-cash-payment.png') });
			await pay.locator('button.btn-success', { hasText: '💵' }).click();
			await modal(p, 'Payment successful');
			await noModal(p, 10000);
			return p;
		},
		'pos-sale-vouchers': async () => {
			const p = await hq(s);
			const pay = await startSale(p, [['Walk Cola', 1]]);
			const button = pay.locator('button.btn-success', { hasText: 'vouchers' });
			note('voucher button: ' + (await button.textContent()).trim());
			await button.click();
			await modal(p, 'Payment successful');
			await noModal(p, 10000);
			return p;
		},
		'pos-sale-cancel-payment': async () => {
			const p = await hq(s);
			const pay = await startSale(p, [['Walk Fries', 1]]);
			await pay.locator('.modal-footer button', { hasText: 'Cancel' }).click();
			const m = await modal(p, 'Payment failed');
			await m.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'pos-settings-live-remote': async () => {
			const p = s.pos;
			await goto(p, '/pos/settings');
			const live = p.locator('#allow_live_orders input[type=checkbox]');
			await live.uncheck();
			await Promise.all([p.waitForEvent('load'), p.click('form button[type=submit]')]);
			await hq(s).catch(() => {});
			await p.locator('.remote-orders').waitFor();
			assert(await p.locator('#live-orders').count() === 0, 'live orders still shown');
			await p.screenshot({ path: s.out('pos-settings-live-remote-hq.png'), fullPage: true });
			await goto(p, '/pos/settings');
			await p.locator('#allow_live_orders input[type=checkbox]').check();
			await Promise.all([p.waitForEvent('load'), p.click('form button[type=submit]')]);
			await hq(s);
			return p;
		},
		'pos-settings-sync': async () => {
			const p = s.pos;
			await goto(p, '/pos/settings');
			await p.locator('button', { hasText: 'Sync now' }).click();
			await p.locator('.alert', { hasText: /Synchronization complete|error|fail/i }).first().waitFor();
			await p.locator('.alert-success', { hasText: 'Synchronization complete.' }).waitFor({ timeout: 1000 });
			await p.locator('button[type=reset]').click();
			return p;
		},
		'pos-settings-logout-cancel': async () => {
			const p = s.pos;
			await goto(p, '/pos/settings');
			dialogs.dismissConfirm = true;
			await p.locator('button', { hasText: 'Logout' }).click();
			await p.waitForTimeout(1000);
			assert(p.url().includes('/pos/settings') && dialogs.log.at(-1).includes('logout'), 'logout confirm not shown or not respected');
			return p;
		},
		'pos-remote-orders-accept': async () => {
			const p = await hq(s);
			const order = p.locator('.remote-order-container .order').first();
			await order.waitFor({ timeout: 30000 });
			await order.locator('button', { hasText: 'Completed' }).click();
			// An unpaid remote order asks for payment first.
			const pay = await modal(p, 'Payment');
			await pay.locator('button.btn-success', { hasText: '💵' }).click();
			const done = await modal(p, 'Order accepted');
			await p.screenshot({ path: s.out('pos-remote-orders-accept-modal.png') });
			await done.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'pos-remote-orders-decline': async () => {
			const p = await hq(s);
			const order = p.locator('.remote-order-container .order').first();
			await order.waitFor({ timeout: 30000 });
			await order.locator('button', { hasText: 'Not accepted' }).click();
			const m = await modal(p, 'Confirm declined order');
			await m.locator('.modal-footer .btn-primary, .modal-footer .btn-danger').first().click();
			const done = await modal(p, 'Order declined');
			await done.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'pos-remote-orders-filters': async () => {
			const p = await hq(s);
			const select = p.locator('.remote-orders select');
			await select.waitFor();
			const options = await select.locator('option').allTextContents();
			const kitchen = options.find((o) => o.includes('Kitchen'));
			assert(kitchen, 'no Kitchen station in the filter: ' + options.join(', '));
			await select.selectOption({ label: kitchen.trim() });
			await p.waitForTimeout(1000);
			await select.selectOption({ index: 0 });
			await p.waitForTimeout(1000);
			const box = p.locator('.remote-orders .custom-control', { hasText: /assigned/i }).locator('label');
			await box.click();
			await p.waitForTimeout(500);
			await box.click();
			await p.waitForTimeout(500);
			return p;
		},
		'pos-summary': async () => {
			const p = s.pos;
			await goto(p, `/pos/events/${s.eventId}/summary`);
			await p.locator('.order-summary table').waitFor();
			await p.locator('.nav-tabs a', { hasText: 'Orderer names' }).click();
			await p.waitForURL(/summary-names/);
			await p.locator('.order-summary table').waitFor();
			return p;
		},
		'pos-sales-history': async () => {
			const p = s.pos;
			await goto(p, `/pos/events/${s.eventId}/sales`);
			await p.locator('.order-history .order').first().waitFor();
			return p;
		},
		'pos-transactions': async () => {
			const p = s.pos;
			await goto(p, '/pos/transactions');
			await p.locator('table, .alert-warning').first().waitFor();
			return p;
		},
		'pos-financial-overview': async () => {
			const p = s.pos;
			await goto(p, '/pos/financial-overview');
			await p.locator('table.table').waitFor();
			return p;
		},
		'pos-test-transactions': async () => {
			const p = s.pos;
			await goto(p, '/pos/tests/transactions');
			await p.locator('.container-fluid').first().waitFor();
			return p;
		},
		'pos-unpaired': async () => {
			const p = s.pos;
			// The deleted device gets 401 from the API on purpose. Chrome logs that status as a console error.
			expectConsoleError(/status of 401 .*devices\/current/);
			await goto(p, '/pos/events');
			await p.locator('.authenticate-card').waitFor({ timeout: 20000 });
			note('dialogs: ' + dialogs.log.slice(-2).join(' / '));
			return p;
		},
		'pos-authenticate-manual-entry': async () => {
			const p = s.pos;
			await p.locator('button', { hasText: 'Enter Token Manually' }).click();
			await p.fill('#tokenInput', 'not-a-valid-token');
			await p.locator('button', { hasText: 'Authenticate' }).click();
			await p.locator('.alert-danger').waitFor();
			await p.screenshot({ path: s.out('pos-authenticate-manual-entry-error.png') });
			await p.locator('button', { hasText: 'Back' }).click();
			await p.locator('button', { hasText: 'Scan QR Code' }).waitFor();
			return p;
		},
	};
}
