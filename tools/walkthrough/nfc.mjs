// Walk-through items for the POS with the NFC simulator (cards, key approval, payments, check-in).
import { SIM, note, sim, goto, text, modal, noModal, rowAction, assert } from './lib.mjs';
import { hq, startSale } from './pos.mjs';

async function navText(p) {
	return text(p, 'nav.navbar');
}

async function waitNav(p, needle, timeout = 15000) {
	await p.locator('nav.navbar').filter({ hasText: needle }).waitFor({ timeout });
}

async function tap(s) {
	await sim('POST', `/cards/${s.cardUid}/tap`);
}

async function removeCard() {
	await sim('POST', '/remove');
}

async function cardsPage(s) {
	const p = s.pos;
	await goto(p, '/pos/cards');
	await p.locator('h1').first().waitFor();
	return p;
}

/** Tap the card on the Cards page and wait for the card view. */
async function showCard(s) {
	const p = await cardsPage(s);
	await removeCard();
	await p.waitForTimeout(1000);
	await tap(s);
	await p.locator('h2', { hasText: 'Card #' }).waitFor({ timeout: 20000 });
	await p.locator('.topup-amounts .amount').first().waitFor({ timeout: 20000 });
	return p;
}

async function balanceRow(p) {
	return text(p, 'table.table tr:has(td:text-is("Balance")) td:nth-child(2)');
}

/** Open the NFC key modal. After a reload the POS can open it by itself. */
async function openKeyModal(p) {
	await p.waitForTimeout(1500);
	if (await p.locator('.modal.show .modal-title', { hasText: 'NFC Card Signing Credentials' }).count() === 0) {
		await p.locator('nav.navbar .btn', { hasText: 'NFC' }).first().click();
	}
	return modal(p, 'NFC Card Signing Credentials');
}

async function confirmTopup(p) {
	const m = await modal(p, 'Confirm topup');
	await m.locator('.modal-footer .btn-primary').click();
	const done = await modal(p, 'Topup successful');
	await done.locator('.modal-footer button').click();
	await noModal(p);
}

export function nfcItems(s) {
	return {
		'nfc-settings-connect': async () => {
			const p = s.pos;
			await sim('DELETE', `/cards/${s.cardUid}`);
			await goto(p, '/pos/settings');
			await p.fill('#nfc-server', SIM);
			await p.fill('#nfc-password', 'walk');
			await Promise.all([p.waitForEvent('load'), p.click('form button[type=submit]')]);
			await p.locator('nav.navbar').waitFor();
			await waitNav(p, 'NFC');
			return p;
		},
		'nfc-key-generate': async () => {
			const p = s.pos;
			const m = await openKeyModal(p);
			await p.screenshot({ path: s.out('nfc-key-generate-none.png') });
			await m.locator('button', { hasText: 'Generate' }).click();
			await m.locator('.alert-warning', { hasText: 'Waiting for approval' }).waitFor({ timeout: 20000 });
			await p.screenshot({ path: s.out('nfc-key-generate-pending.png') });
			await m.locator('button', { hasText: 'Check' }).click();
			await p.waitForTimeout(1500);
			await m.locator('.alert-warning', { hasText: 'Waiting for approval' }).waitFor();
			await m.locator('.modal-footer button', { hasText: 'Close' }).click();
			await noModal(p);
			await waitNav(p, 'NFC ⏳');
			return p;
		},
		'manage-devices-approve-key': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await p.locator('tr', { hasText: s.deviceName }).locator('.badge', { hasText: 'Pending' }).waitFor();
			await rowAction(p, s.deviceName, 'Approve');
			await p.locator('tr', { hasText: s.deviceName }).locator('.badge-success', { hasText: 'Approved' }).waitFor();
			return p;
		},
		'nfc-reader-ready': async () => {
			const p = s.pos;
			const m = await openKeyModal(p);
			await m.locator('button', { hasText: 'Check' }).click();
			await m.locator('.alert-success', { hasText: 'Credentials approved' }).waitFor({ timeout: 20000 });
			await p.screenshot({ path: s.out('nfc-reader-ready-approved.png') });
			await m.locator('.modal-footer button', { hasText: 'Close' }).click();
			await noModal(p);
			await p.reload();
			await p.locator('nav.navbar .btn-success', { hasText: 'NFC' }).waitFor({ timeout: 20000 });
			return p;
		},
		'nfc-card-first-tap': async () => {
			const p = await cardsPage(s);
			await tap(s);
			await p.waitForTimeout(4000);
			const nav = await navText(p);
			note('navbar after the first tap: ' + nav);
			if (nav.includes('Corrupt card')) {
				note('known: a blank card shows "Corrupt card" on its first tap when min_nfc_version is 1; removed and tapped again');
				await removeCard();
				await p.waitForTimeout(1500);
				await tap(s);
			}
			await waitNav(p, 'Balance: 0.00', 20000);
			await p.locator('h2', { hasText: 'Card #' }).waitFor();
			s.cardId = (await text(p, 'table.table tr:has(td:text-is("ID")) td:nth-child(2)'));
			note('card id ' + s.cardId);
			return p;
		},
		'nfc-card-topup-default': async () => {
			const p = s.pos;
			await p.locator('.topup-amounts .amount').first().click();
			await confirmTopup(p);
			await waitNav(p, 'Balance: 5.00', 20000);
			return p;
		},
		'nfc-card-topup-custom': async () => {
			const p = s.pos;
			await p.fill('#customAmount', '7.50');
			await p.locator('button', { hasText: /^\s*Topup\s*$/ }).click();
			await confirmTopup(p);
			await waitNav(p, 'Balance: 12.50', 20000);
			return p;
		},
		'nfc-card-remove-retap': async () => {
			const p = s.pos;
			await removeCard();
			await p.waitForFunction(() => !document.querySelector('nav.navbar').textContent.includes('Balance:'), null, { timeout: 15000 });
			await p.screenshot({ path: s.out('nfc-card-remove-retap-removed.png') });
			await tap(s);
			await waitNav(p, 'Balance: 12.50', 20000);
			await p.locator('table', { hasText: 'topup' }).first().waitFor();
			return p;
		},
		'nfc-card-aliases': async () => {
			const p = s.pos;
			const add = p.locator('li', { has: p.locator('button', { hasText: 'Add' }) });
			await add.locator('input[type=text]').fill('walk-tmp-alias');
			await add.locator('button', { hasText: 'Add' }).click();
			const li = p.locator('li', { hasText: 'walk-tmp-alias' });
			await li.waitFor();
			await li.locator('a').click();
			await li.waitFor({ state: 'detached' });
			return p;
		},
		'nfc-card-discount': async () => {
			const p = s.pos;
			const input = () => p.locator('input[type=number][max="100.0"]');
			const save = async (value) => {
				await input().fill(value);
				await p.locator('p', { has: input() }).locator('button', { hasText: 'Save' }).click();
				await p.locator('span', { hasText: /^Saved$/ }).waitFor({ timeout: 15000 });
			};
			// Read the card again, so that the value comes from the card and not from the form.
			const reread = async () => {
				await removeCard();
				await p.waitForFunction(() => !document.querySelector('nav.navbar').textContent.includes('Balance:'), null, { timeout: 15000 });
				await tap(s);
				await input().waitFor({ timeout: 20000 });
				await p.waitForTimeout(1000);
				return input().inputValue();
			};
			await save('10');
			const ten = await reread();
			assert(ten === '10', 'discount after the re-read: expected 10, got ' + ten);
			await save('0');
			const zero = await reread();
			assert(zero === '0', 'discount after the re-read: expected 0, got ' + zero);
			return p;
		},
		'nfc-card-payment': async () => {
			const p = await hq(s);
			await removeCard();
			await p.waitForTimeout(1000);
			const pay = await startSale(p, [['Walk Beer', 1]]);
			await p.screenshot({ path: s.out('nfc-card-payment-popup.png') });
			await tap(s);
			await modal(p, 'Payment successful');
			await noModal(p, 15000);
			await waitNav(p, 'Balance: 10.00', 20000);
			return p;
		},
		'nfc-card-insufficient-funds': async () => {
			const p = await hq(s);
			await removeCard();
			await p.waitForTimeout(1000);
			const pay = await startSale(p, [['Walk Fries', 4]]);
			await tap(s);
			await pay.locator('.alert-warning', { hasText: 'Card error' }).waitFor({ timeout: 20000 });
			note('popup message: ' + (await text(p, '.modal.show .alert-warning')));
			await p.screenshot({ path: s.out('nfc-card-insufficient-funds-popup.png') });
			await pay.locator('.modal-footer button', { hasText: 'Cancel' }).click();
			const m = await modal(p, 'Payment failed');
			await m.locator('.modal-footer button').click();
			await noModal(p);
			await waitNav(p, 'Balance: 10.00');
			return p;
		},
		'nfc-checkin': async () => {
			const p = s.pos;
			await removeCard();
			await goto(p, `/pos/events/${s.eventId}/check-in`);
			await p.locator('p', { hasText: 'Scan to start' }).waitFor();
			await tap(s);
			await p.locator('h3', { hasText: 'Select attendee' }).waitFor({ timeout: 20000 });
			await p.screenshot({ path: s.out('nfc-checkin-select.png'), fullPage: true });
			await p.locator('.attendee', { hasText: 'Walk Attendee One' }).click();
			const m = await modal(p, 'Confirm check-in');
			await m.locator('.modal-footer .btn-primary, .modal-footer .btn-success').first().click();
			await noModal(p);
			await p.locator('h2', { hasText: 'Card #' }).waitFor({ timeout: 20000 });
			await p.locator('li', { hasText: 'walk-alias-1' }).first().waitFor();
			return p;
		},
		'nfc-cards-transactions-link': async () => {
			const p = await cardsPage(s);
			await p.locator('h1 a', { hasText: /Transactions/i }).click();
			await p.waitForURL(/\/transactions/);
			await p.locator('table').first().waitFor();
			return p;
		},
		'nfc-card-reset': async () => {
			const p = await showCard(s);
			await p.locator('button.btn-info', { hasText: 'Reset' }).click();
			const done = await modal(p, 'Card reset successful');
			await done.locator('.modal-footer button').click();
			await noModal(p);
			await waitNav(p, 'Balance: 0.00', 20000);
			return p;
		},
		'nfc-card-rebuild': async () => {
			const p = s.pos;
			await p.locator('button.btn-danger', { hasText: 'Rebuild' }).last().click();
			await p.waitForTimeout(5000);
			note('dialogs: ' + s.lastDialogs(2));
			assert(s.lastDialogs(1).includes('rebuilt'), 'no "Card is rebuilt" alert');
			return p;
		},
		'manage-devices-revoke-key': async () => {
			const p = s.manage;
			await goto(p, '/manage/devices');
			await rowAction(p, s.deviceName, 'Revoke');
			await p.waitForFunction((n) => {
				const tr = [...document.querySelectorAll('tr')].find((t) => t.textContent.includes(n));
				return tr && !tr.textContent.includes('Approved');
			}, s.deviceName, { timeout: 15000 });
			return p;
		},
		'nfc-key-revoked-regenerate': async () => {
			const p = s.pos;
			await p.reload();
			await waitNav(p, 'NFC 🔑', 20000);
			const m = await openKeyModal(p);
			await p.screenshot({ path: s.out('nfc-key-revoked-regenerate-revoked.png') });
			await m.locator('button', { hasText: 'Generate' }).click();
			await m.locator('.alert-warning', { hasText: 'Waiting for approval' }).waitFor({ timeout: 20000 });
			await m.locator('.modal-footer button', { hasText: 'Close' }).click();
			await noModal(p);
			return p;
		},
		'manage-public-keys-approve': async () => {
			const p = s.manage;
			await goto(p, '/manage/public-keys');
			const row = p.locator('tr', { hasText: s.deviceName }).filter({ hasText: 'Pending' }).first();
			await row.waitFor();
			await row.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Approve' }).click();
			await p.locator('tr', { hasText: s.deviceName }).filter({ hasText: 'Approved' }).first().waitFor();
			await s.pos.reload();
			await s.pos.locator('nav.navbar .btn-success', { hasText: 'NFC' }).waitFor({ timeout: 20000 });
			return p;
		},
	};
}
