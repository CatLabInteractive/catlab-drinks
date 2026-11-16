// Walk-through items for the Manage app (/manage/).
import { RUN, dialogs, note, goto, modal, noModal, rowAction, assert, localUrl } from './lib.mjs';

async function menuRows(p) {
	return p.locator('.menu-table tbody tr');
}

async function rowNames(p) {
	return p.$$eval('.menu-table tbody tr input[data-column="0"]', (els) => els.map((e) => e.value).filter(Boolean));
}

async function saveMenu(p) {
	const save = p.locator('button', { hasText: 'Save changes' });
	await save.click();
	await p.waitForFunction(() => {
		const b = [...document.querySelectorAll('button')].find((x) => x.textContent.includes('Save changes'));
		return b && b.disabled;
	}, null, { timeout: 20000 });
	await p.waitForTimeout(500);
}

async function addProduct(p, name, price) {
	const rows = await menuRows(p);
	const index = (await rows.count()) - 1;
	await rows.last().locator('input[data-column="0"]').fill(name);
	await rows.nth(index).locator('input[data-column="2"]').fill(price);
	return rows.nth(index);
}

async function chooseOrCreate(p, row, column, name) {
	const select = row.locator(`select[data-column="${column}"]`);
	const labels = await select.locator('option').allTextContents();
	if (labels.map((l) => l.trim()).includes(name)) {
		await select.selectOption({ label: name });
		return;
	}
	dialogs.promptAnswer = name;
	await select.selectOption('new');
	await p.waitForFunction(([sel, n]) => {
		const s = document.querySelectorAll('.menu-table tbody tr')[sel.row].querySelector(`select[data-column="${sel.col}"]`);
		return s && s.selectedOptions[0] && s.selectedOptions[0].textContent.trim() === n;
	}, [{ row: Number(await row.getAttribute('data-row-index')), col: column }, name], { timeout: 15000 });
}

async function openEventsRow(s, name) {
	const p = s.manage;
	await goto(p, '/manage/events');
	await p.locator('tr', { hasText: name }).first().waitFor();
	return p;
}

async function createEvent(p, name, { cards = true, cash = true, vouchers = false, ordering = 'Yes, and we handle payment ourselves' } = {}) {
	await p.click(`button[title="Create new event"]`);
	const m = p.locator('.modal.show');
	await m.locator('.event-form').waitFor();
	await m.locator('.event-form input[type=text]').first().fill(name);
	await m.locator('button', { hasText: 'Next' }).click();
	if (cards) await m.locator('button.event-tile', { hasText: 'NFC topup cards' }).click();
	if (cash) await m.locator('button.event-tile', { hasText: 'Cash' }).click();
	if (vouchers) await m.locator('button.event-tile', { hasText: 'Vouchers' }).click();
	await m.locator('button', { hasText: 'Next' }).click();
	await m.locator('button.event-tile', { hasText: ordering }).click();
	await m.locator('button', { hasText: 'Create event' }).click();
	await noModal(p);
	await p.locator('tr', { hasText: name }).first().waitFor();
	const href = await p.locator('tr', { hasText: name }).first().locator('a[href*="/events/"]').first().getAttribute('href');
	return Number(href.match(/events\/(\d+)/)[1]);
}

export function manageItems(s) {
	return {
		// ---- Manage: events ----
		'manage-events-list': async () => {
			const p = s.manage;
			await goto(p, '/manage/');
			await p.locator('h1', { hasText: 'Events' }).waitFor();
			await p.locator('.card', { hasText: 'POS Device Pairing' }).waitFor();
			note('test mode banner shown: ' + (await p.locator('.alert-warning', { hasText: 'testing mode' }).count() > 0));
			return p;
		},
		'manage-events-create': async () => {
			const p = s.manage;
			await goto(p, '/manage/events');
			s.eventName = `Walk Event ${RUN}`;
			s.eventId = await createEvent(p, s.eventName);
			return p;
		},
		'manage-events-wizard-back-cancel': async () => {
			const p = s.manage;
			await goto(p, '/manage/events');
			await p.click('button[title="Create new event"]');
			const m = p.locator('.modal.show');
			await m.locator('.event-form input[type=text]').first().fill('Never saved ' + RUN);
			await m.locator('button', { hasText: 'Next' }).click();
			await m.locator('button.event-tile', { hasText: 'Cash' }).click();
			await m.locator('button', { hasText: 'Next' }).click();
			await m.locator('button', { hasText: 'Back' }).click();
			await m.locator('button.event-tile', { hasText: 'Cash' }).waitFor();
			await m.locator('button', { hasText: 'Cancel' }).click();
			await noModal(p);
			assert(await p.locator('tr', { hasText: 'Never saved ' + RUN }).count() === 0, 'cancelled event was created');
			return p;
		},
		'manage-events-toggle-open': async () => {
			const p = await openEventsRow(s, s.eventName);
			const row = p.locator('tr', { hasText: s.eventName }).first();
			for (let i = 0; i < 2; i++) {
				const before = (await row.locator('td button.btn-success, td button.btn-danger').first().textContent()).trim();
				await row.locator('td button', { hasText: /^\s*(Open|Closed)\s*$/ }).first().click();
				await p.waitForFunction(([name, b]) => {
					const tr = [...document.querySelectorAll('tr')].find((t) => t.textContent.includes(name));
					const btn = tr && [...tr.querySelectorAll('button')].find((x) => /^(Open|Closed)$/.test(x.textContent.trim()));
					return btn && btn.textContent.trim() !== b;
				}, [s.eventName, before]);
			}
			// End in the "Open" state, so that the client can order.
			if ((await row.locator('button', { hasText: /^\s*Closed\s*$/ }).count()) > 0) {
				await row.locator('button', { hasText: /^\s*Closed\s*$/ }).click();
				await row.locator('button', { hasText: /^\s*Open\s*$/ }).waitFor();
			}
			return p;
		},
		'manage-events-edit': async () => {
			const p = await openEventsRow(s, s.eventName);
			await rowAction(p, s.eventName, 'Edit');
			const m = p.locator('.modal.show');
			await m.locator('.event-form').waitFor();
			await p.waitForTimeout(500);
			// b-form-checkbox "Split orders per station": on, then off again.
			const split = m.locator('.custom-control', { hasText: 'Split orders per station' }).locator('label');
			await split.click();
			await split.click();
			await m.locator('button.event-tile', { hasText: 'Vouchers' }).click();
			await m.locator('.event-form input[inputmode=decimal]').fill('2,50');
			s.eventName = `Walk Event ${RUN} edited`;
			await m.locator('.event-form input[type=text]').first().fill(s.eventName);
			await m.locator('button', { hasText: 'Save' }).click();
			await noModal(p);
			await p.locator('tr', { hasText: s.eventName }).first().waitFor();
			return p;
		},
		'manage-events-order-token': async () => {
			const p = await openEventsRow(s, s.eventName);
			await rowAction(p, s.eventName, 'Remote order token');
			const m = await modal(p, 'Remote order token');
			const values = await m.locator('input.order-token').evaluateAll((els) => els.map((e) => e.value));
			assert(values.length === 2 && values[0].includes('/order/') && values[1].includes('-'), 'unexpected token modal values: ' + values.join(', '));
			note('order url host: ' + new URL(values[0]).host);
			s.orderUrl = localUrl(values[0]);
			await p.screenshot({ path: s.out('manage-events-order-token-open.png') });
			await m.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'manage-events-actions-links': async () => {
			const p = s.manage;
			const links = [['Edit menu', '/menu'], ['Sales overview', '/summary'], ['Order history', '/sales'], ['Register attendees', '/attendees']];
			for (const [label, path] of links) {
				await openEventsRow(s, s.eventName);
				await rowAction(p, s.eventName, label);
				await p.waitForURL(new RegExp(`/events/${s.eventId}${path}`));
			}
			await openEventsRow(s, s.eventName);
			await p.locator('tr', { hasText: s.eventName }).first().locator('.dropdown-toggle').click();
			const href = await p.locator('.dropdown-menu.show a', { hasText: 'Client order form' }).getAttribute('href');
			assert(href && href.includes('/order/'), 'client order form link: ' + href);
			note('client order form link host (not opened): ' + new URL(href).host);
			await p.keyboard.press('Escape');
			return p;
		},
		'manage-language-toggle': async () => {
			const p = s.manage;
			await goto(p, '/manage/events');
			const dd = p.locator('.navbar .nav-item.dropdown').filter({ has: p.locator('.dropdown-menu a', { hasText: /Nederlands|English/ }) }).first();
			await dd.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Nederlands' }).click();
			await p.locator('h1', { hasText: 'Evenementen' }).waitFor();
			await p.screenshot({ path: s.out('manage-language-toggle-nl.png') });
			await dd.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'English' }).click();
			await p.locator('h1', { hasText: 'Events' }).waitFor();
			return p;
		},
		'manage-navbar-settings-dropdown': async () => {
			const p = s.manage;
			await goto(p, '/manage/events');
			await p.locator('.navbar .dropdown-toggle', { hasText: 'Settings' }).click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Public Keys' }).click();
			await p.waitForURL(/public-keys/);
			await p.locator('.navbar .dropdown-toggle', { hasText: 'Settings' }).click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Organisation Settings' }).click();
			await p.waitForURL(/\/settings/);
			return p;
		},
		'manage-navbar-mobile': async () => {
			const p = s.manage;
			await p.setViewportSize({ width: 390, height: 844 });
			try {
				await goto(p, '/manage/events');
				await p.locator('.navbar-toggler').click();
				await p.locator('#nav_collapse.show a', { hasText: 'Points of sale' }).waitFor();
				await p.screenshot({ path: s.out('manage-navbar-mobile.png'), fullPage: true });
				await p.locator('#nav_collapse a', { hasText: 'Points of sale' }).click();
				await p.waitForURL(/\/devices/);
			} finally {
				await p.setViewportSize({ width: 1280, height: 900 });
			}
			return null;
		},

		// ---- Manage: menu ----
		'manage-menu-add-products': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/menu`);
			await p.locator('.menu-table').waitFor();
			const beer = await addProduct(p, 'Walk Beer', '2,50');
			await chooseOrCreate(p, beer, 4, 'Bar');
			await chooseOrCreate(p, beer, 5, 'Drinks');
			const cola = await addProduct(p, 'Walk Cola', '2');
			await chooseOrCreate(p, cola, 4, 'Bar');
			await chooseOrCreate(p, cola, 5, 'Drinks');
			const fries = await addProduct(p, 'Walk Fries', '3,50');
			await chooseOrCreate(p, fries, 4, 'Kitchen');
			await chooseOrCreate(p, fries, 5, 'Food');
			await saveMenu(p);
			await goto(p, `/manage/events/${s.eventId}/menu`);
			await p.locator('.menu-table').waitFor();
			const names = await rowNames(p);
			assert(['Walk Beer', 'Walk Cola', 'Walk Fries'].every((n) => names.includes(n)), 'products after reload: ' + names.join(', '));
			return p;
		},
		'manage-menu-selling-delete-undo': async () => {
			const p = s.manage;
			const row = p.locator('.menu-table tbody tr').first();
			await row.locator('button', { hasText: /^\s*Selling\s*$/ }).click();
			await row.locator('button', { hasText: 'Not selling' }).waitFor();
			await row.locator('button', { hasText: 'Not selling' }).click();
			await row.locator('button[title="Delete"]').click();
			await row.locator('button[title="Undo delete"]').waitFor();
			await row.locator('button[title="Undo delete"]').click();
			await row.locator('button[title="Delete"]').waitFor();
			return p;
		},
		'manage-menu-reset': async () => {
			const p = s.manage;
			const first = p.locator('.menu-table tbody tr').first().locator('input[data-column="1"]');
			await first.fill('Unsaved description');
			await p.locator('button', { hasText: /^\s*Reset\s*$/ }).click();
			await p.waitForFunction(() => {
				const i = document.querySelector('.menu-table tbody tr input[data-column="1"]');
				return i && i.value !== 'Unsaved description';
			});
			return p;
		},
		'manage-menu-leave-unsaved': async () => {
			const p = s.manage;
			await p.locator('.menu-table tbody tr').first().locator('input[data-column="1"]').fill('Unsaved again');
			dialogs.dismissConfirm = true;
			await p.locator('.navbar a', { hasText: 'Events' }).click();
			await p.waitForTimeout(1000);
			assert(p.url().includes('/menu'), 'left the page although the confirm was dismissed');
			dialogs.dismissConfirm = false;
			await p.locator('.navbar a', { hasText: 'Events' }).click();
			await p.waitForURL(/\/events$/);
			assert(dialogs.log.slice(-2).every((d) => d.includes('unsaved changes')), 'expected two "unsaved changes" confirms');
			return p;
		},
		'manage-menu-reorder-drag': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/menu`);
			await p.locator('.menu-table').waitFor();
			const before = await rowNames(p);
			await p.locator('.menu-table tbody tr').nth(1).locator('td.menu-handle').dragTo(p.locator('.menu-table tbody tr').nth(0));
			const after = await rowNames(p);
			assert(after[0] === before[1], `drag did not move the row: ${before.join(',')} -> ${after.join(',')}`);
			await saveMenu(p);
			return p;
		},
		'manage-menu-headings-rename': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/menu`);
			const input = p.locator('.menu-headings-table input').first();
			await input.waitFor();
			const old = await input.inputValue();
			await input.fill(old + ' ' + RUN);
			await saveMenu(p);
			await goto(p, `/manage/events/${s.eventId}/menu`);
			assert((await p.locator('.menu-headings-table input').first().inputValue()) === old + ' ' + RUN, 'heading rename not saved');
			await p.locator('.menu-headings-table input').first().fill(old);
			await saveMenu(p);
			return p;
		},
		'manage-menu-import-paste': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/menu`);
			await p.locator('button', { hasText: 'Import menu' }).click();
			const m = await modal(p, 'Import menu');
			await m.locator('.menu-import-table tbody input').first().evaluate((el) => {
				const dt = new DataTransfer();
				dt.setData('text/plain', 'Walk Water\t1,80\nWalk Tea\t2,20\n');
				el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
			});
			await m.locator('.menu-import-heading').selectOption({ label: 'Drinks' });
			await p.screenshot({ path: s.out('manage-menu-import-paste-modal.png') });
			await p.locator('.modal.show .menu-import-add', { hasText: 'Add 2 products' }).click();
			await noModal(p);
			await saveMenu(p);
			const names = await rowNames(p);
			assert(names.includes('Walk Water') && names.includes('Walk Tea'), 'imported rows missing: ' + names.join(', '));
			return p;
		},
		'manage-menu-import-cancel': async () => {
			const p = s.manage;
			await p.locator('button', { hasText: 'Import menu' }).click();
			const m = await modal(p, 'Import menu');
			await m.locator('.menu-import-table tbody input').first().fill('Something');
			await p.locator('.modal.show .modal-footer button', { hasText: 'Cancel' }).click();
			await noModal(p);
			assert(dialogs.log.some((d) => d.includes('Discard the pasted menu')), 'no discard confirm');
			return p;
		},
		'manage-menu-import-copy-event': async () => {
			const p = s.manage;
			await goto(p, '/manage/events');
			s.eventBName = `Walk Copy ${RUN}`;
			s.eventBId = await createEvent(p, s.eventBName, { cards: false });
			await goto(p, `/manage/events/${s.eventBId}/menu`);
			await p.locator('button', { hasText: 'Import menu' }).click();
			const m = await modal(p, 'Import menu');
			await m.locator('#menu-import-copy-event').selectOption({ label: s.eventName });
			await p.locator('.modal.show .menu-import-add', { hasText: /Add [1-9]\d* products/ }).waitFor();
			await p.screenshot({ path: s.out('manage-menu-import-copy-event-modal.png') });
			await p.locator('.modal.show .menu-import-add').click();
			await noModal(p, 20000);
			await saveMenu(p);
			const names = await rowNames(p);
			assert(names.includes('Walk Beer'), 'copied rows missing: ' + names.join(', '));
			return p;
		},
		'manage-events-delete': async () => {
			const p = await openEventsRow(s, s.eventBName);
			await rowAction(p, s.eventBName, 'Delete');
			await p.waitForFunction((n) => ![...document.querySelectorAll('tr')].some((t) => t.textContent.includes(n)), s.eventBName);
			return p;
		},

		// ---- Manage: organisation settings ----
		'manage-settings-currency': async () => {
			const p = s.manage;
			await goto(p, '/manage/settings');
			const sel = p.locator('#organisation-currency');
			await sel.waitFor();
			const original = await sel.inputValue();
			await sel.selectOption('USD');
			await p.locator('form button[type=submit]', { hasText: 'Save' }).click();
			await p.locator('.alert-success', { hasText: 'Saved' }).waitFor();
			await goto(p, '/manage/settings');
			assert((await p.locator('#organisation-currency').inputValue()) === 'USD', 'currency not saved');
			await p.locator('#organisation-currency').selectOption(original);
			await p.locator('form button[type=submit]', { hasText: 'Save' }).click();
			await p.locator('.alert-success', { hasText: 'Saved' }).waitFor();
			return p;
		},
		'manage-settings-gateway-add': async () => {
			const p = s.manage;
			await goto(p, '/manage/settings');
			await p.locator('button', { hasText: 'Add Payment Gateway' }).click();
			const m = await modal(p, 'Add Payment Gateway');
			await m.locator('#gateway-type').selectOption('paynl');
			await m.locator('#paynl-api-token').fill('AT-0000-0000');
			await m.locator('#paynl-api-secret').fill('walk-secret');
			await m.locator('#paynl-service-id').fill('SL-0000-0000');
			await p.screenshot({ path: s.out('manage-settings-gateway-add-modal.png') });
			await m.locator('.modal-footer button', { hasText: 'Save' }).click();
			await noModal(p);
			await p.locator('table tr', { hasText: /pay\.?nl/i }).first().waitFor();
			return p;
		},
		'manage-settings-gateway-edit-delete': async () => {
			const p = s.manage;
			await rowAction(p, 'Pay', 'Edit');
			const m = await modal(p, 'Edit Payment Gateway');
			await m.locator('#paynl-service-id').fill('SL-1111-1111');
			await m.locator('.modal-footer button', { hasText: 'Save' }).click();
			await noModal(p);
			await rowAction(p, 'Pay', 'Delete');
			await p.locator('.alert-info').first().waitFor();
			assert(await p.locator('table tr', { hasText: /pay\.?nl/i }).count() === 0, 'gateway still listed');
			return p;
		},

		// ---- Manage: devices and keys (no device yet) ----
		'manage-devices-list': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await p.locator('button[title="Pair a device"]').waitFor();
			return p;
		},
		'manage-devices-pair-cancel': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await p.click('button[title="Pair a device"]');
			const m = await modal(p, 'Connect & authenticate a device');
			await m.locator('#connect-url').waitFor();
			await m.locator('canvas, svg, img').first().waitFor();
			await m.locator('.modal-footer button', { hasText: 'Cancel' }).click();
			await noModal(p);
			return p;
		},
		'manage-public-keys-empty': async () => {
			const p = s.manage;
			await goto(p, '/manage/public-keys');
			await p.locator('h1', { hasText: 'Public Keys' }).waitFor();
			return p;
		},

		// ---- Manage: attendees (spreadsheet) ----
		'manage-attendees-edit': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/attendees`);
			await p.locator('.spreadsheet-table').waitFor();
			const cells = p.locator('.spreadsheet-table tbody tr').first().locator('input.cell-input');
			await cells.nth(0).fill('walk-alias-1');
			await cells.nth(1).fill('Walk Attendee One');
			await cells.nth(2).fill('one@example.test');
			const before = await p.locator('.spreadsheet-table tbody tr').count();
			await p.locator('button', { hasText: 'Add rows' }).click();
			assert(await p.locator('.spreadsheet-table tbody tr').count() === before + 5, 'Add rows did not add 5 rows');
			await p.locator('button', { hasText: 'Save changes' }).click();
			await p.locator('.alert-success', { hasText: 'Saved' }).waitFor();
			await goto(p, `/manage/events/${s.eventId}/attendees`);
			assert(await p.locator('.spreadsheet-table tbody tr').first().locator('input.cell-input').nth(1).inputValue() === 'Walk Attendee One', 'attendee not saved');
			return p;
		},
		'manage-attendees-paste-dialog': async () => {
			const p = s.manage;
			await goto(p, `/manage/events/${s.eventId}/attendees`);
			await p.locator('.spreadsheet-table').waitFor();
			await p.locator('.spreadsheet-table tbody tr').nth(1).locator('input.cell-input').first().evaluate((el) => {
				const dt = new DataTransfer();
				dt.setData('text/plain', 'walk-alias-2\tWalk Attendee Two\ttwo@example.test\nwalk-alias-3\tWalk Attendee Three\tthree@example.test\n');
				el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
			});
			const m = await modal(p, 'Import pasted data');
			// The dialog guesses the first column separator; pick "Tab" for both selects.
			const selects = m.locator('select');
			await selects.nth(0).selectOption({ label: 'Tab' });
			await selects.nth(1).selectOption({ label: 'Tab' });
			await m.locator('table tbody tr').first().waitFor();
			await p.screenshot({ path: s.out('manage-attendees-paste-dialog-open.png') });
			await m.locator('.modal-footer button', { hasText: 'Import' }).click();
			await noModal(p);
			await p.locator('button', { hasText: 'Save changes' }).click();
			await p.locator('.alert-success', { hasText: 'Saved' }).waitFor();
			const names = await p.$$eval('.spreadsheet-table tbody tr', (rows) => rows.map((r) => r.querySelectorAll('input')[1]?.value).filter(Boolean));
			assert(names.includes('Walk Attendee Two'), 'pasted attendees missing: ' + names.join(', '));
			return p;
		},
	};
}
