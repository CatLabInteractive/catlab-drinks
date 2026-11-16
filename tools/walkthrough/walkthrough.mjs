#!/usr/bin/env node
// Frontend walk-through: runs the `auto` items of docs/testing/frontend-walkthrough.md in a real Chrome.
// Usage and environment variables: tools/walkthrough/README.md
import fs from 'node:fs';
import path from 'node:path';
import { addressProblems } from './localAddress.mjs';
import { OUT, BASE, SIM, CARD_UID, results, dialogs, launch, newPage, runItem, writeReport, betweenItemsResult } from './lib.mjs';
import { bladeItems } from './blade.mjs';
import { manageItems } from './manage.mjs';
import { posItems } from './pos.mjs';
import { nfcItems } from './nfc.mjs';
import { clientItems } from './client.mjs';
import { sharedItems } from './shared.mjs';

// The order matters: later items use the event, the paired device, and the card of earlier items.
const SEQUENCE = [
	// Blade pages and Manage, before any device exists
	'blade-setup', 'blade-getting-started', 'blade-register-closed', 'blade-login-wrong-password', 'blade-welcome',
	'manage-events-list', 'manage-events-create', 'manage-events-wizard-back-cancel', 'manage-events-toggle-open',
	'manage-events-edit', 'manage-events-order-token', 'manage-events-actions-links',
	'manage-language-toggle', 'manage-navbar-settings-dropdown', 'manage-navbar-mobile',
	'manage-menu-add-products', 'manage-menu-selling-delete-undo', 'manage-menu-reset', 'manage-menu-leave-unsaved',
	'manage-menu-reorder-drag', 'manage-menu-headings-rename', 'manage-menu-import-paste', 'manage-menu-import-cancel',
	'manage-menu-import-copy-event', 'manage-events-delete',
	'manage-settings-currency', 'manage-settings-gateway-add', 'manage-settings-gateway-edit-delete',
	'manage-devices-list', 'manage-devices-pair-cancel', 'manage-devices-polling-stops', 'manage-sales-history-polling-stops',
	'manage-public-keys-empty',
	'manage-attendees-edit', 'manage-attendees-paste-dialog',
	// Pairing and the POS
	'manage-devices-pair', 'pos-events', 'pos-language-toggle', 'pos-menu', 'pos-hq', 'pos-hq-polling-stops', 'pos-sale-cash', 'pos-sale-vouchers', 'pos-sale-cancel-payment',
	'pos-settings-live-remote', 'pos-settings-sync', 'pos-settings-logout-cancel',
	// Client order and remote orders on the POS
	'client-order-load', 'client-order-empty', 'client-order-place', 'client-language-toggle',
	'pos-remote-orders-accept', 'client-order-place-second', 'pos-remote-orders-decline', 'pos-remote-orders-filters',
	// POS with the NFC simulator
	'nfc-settings-connect', 'nfc-key-generate', 'manage-devices-approve-key', 'nfc-reader-ready',
	'nfc-card-first-tap', 'nfc-card-topup-default', 'nfc-card-topup-custom', 'nfc-card-remove-retap',
	'nfc-card-aliases', 'nfc-card-discount', 'nfc-card-payment', 'nfc-card-insufficient-funds',
	'nfc-checkin', 'nfc-cards-transactions-link',
	// Shared views, after sales exist
	'manage-summary', 'manage-summary-names', 'manage-summary-detail-modal', 'manage-sales-history',
	'manage-transactions', 'manage-card-details-topup', 'manage-financial-overview', 'manage-cards', 'manage-test-transactions', 'manage-hello',
	'pos-summary', 'pos-sales-history', 'pos-transactions', 'pos-financial-overview', 'pos-test-transactions', 'pos-hello', 'pos-attendees',
	'manage-devices-signed-cards', 'manage-public-keys-list', 'manage-devices-license', 'manage-devices-buy-license-link',
	'manage-devices-edit', 'pos-disabled-device',
	// Card reset and rebuild, key revoke and approval from the Public Keys page
	'nfc-card-reset', 'nfc-card-rebuild', 'manage-devices-revoke-key', 'nfc-key-revoked-regenerate', 'manage-public-keys-approve',
	// Blade pages that need a card, then the end of the device and the session
	'blade-topup-not-available', 'blade-qr-generator', 'blade-landing-pages', 'blade-docs', 'blade-home',
	'manage-devices-delete', 'pos-unpaired', 'pos-authenticate-manual-entry',
	'manage-logout', 'blade-login',
];

async function main() {
	// The route guard sees only the browser. The script's own requests go to WALK_BASE_URL and WALK_NFC_SIM,
	// so refuse a value that is not local before anything is requested.
	const problems = addressProblems({ WALK_BASE_URL: BASE, WALK_NFC_SIM: SIM });
	if (problems.length) {
		for (const p of problems) console.error('Refused: ' + p);
		process.exitCode = 2;
		return;
	}
	const only = process.env.WALK_ONLY ? new RegExp(process.env.WALK_ONLY) : null;
	const queue = SEQUENCE.filter((id) => !only || only.test(id));
	if (!queue.length) {
		console.error(`WALK_ONLY=${process.env.WALK_ONLY} matches no item. Nothing to run.`);
		process.exitCode = 2;
		return;
	}
	fs.rmSync(OUT, { recursive: true, force: true });
	fs.mkdirSync(OUT, { recursive: true });
	console.log(`Walk-through against ${BASE}, NFC simulator ${SIM}`);

	const browser = await launch();
	console.log('Browser: Chrome ' + browser.version());
	const s = {
		browser,
		cardUid: CARD_UID,
		out: (name) => path.join(OUT, name),
		lastDialogs: (n) => dialogs.log.slice(-n).join(' / '),
	};
	s.manage = await newPage(browser, 'manage');
	s.client = await newPage(browser, 'client');
	let anon = null;
	s.anon = async () => (anon ||= await newPage(browser, 'anon'));

	const items = { ...bladeItems(s), ...manageItems(s), ...posItems(s), ...nfcItems(s), ...clientItems(s), ...sharedItems(s) };
	const missing = SEQUENCE.filter((id) => !items[id]);
	if (missing.length) throw new Error('no function for: ' + missing.join(', '));

	try {
		for (let i = 0; i < queue.length; i++) {
			const result = await runItem(queue[i], items[queue[i]]);
			writeReport();
			if (result.timedOut) {
				// The timed-out item may still drive the shared pages. Do not run anything else on them.
				for (const id of queue.slice(i + 1)) {
					results.push({ id, status: 'fail', consoleErrors: [], note: `not run: an earlier item timed out (${queue[i]})` });
				}
				console.log(`Stopped: ${queue[i]} timed out. ${queue.length - i - 1} later items are marked as not run.`);
				break;
			}
		}
	} finally {
		writeReport();
		await browser.close();
	}
	const all = [...results, betweenItemsResult()];
	const failed = all.filter((r) => r.status !== 'pass');
	if (failed.at(-1)?.id === 'between-items') console.log('FAIL between-items: errors arrived while no item ran (see report.json)');
	console.log(`\n${all.length - failed.length} passed, ${failed.length} failed. Report: ${path.join(OUT, 'report.json')}`);
	process.exitCode = failed.length ? 1 : 0;
}

main().catch((e) => {
	console.error(e);
	process.exitCode = 1;
});
