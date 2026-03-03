/**
 * Tests for Settings sync/logout warning features:
 *
 * 1. Settings.vue shows pending queue items count
 * 2. Settings.vue has a "Sync now" button with spinner and error display
 * 3. Settings.vue logout attempts sync first and warns about pending data
 * 4. AbstractOfflineQueue has static getAllPendingCount and uploadAllPending methods
 * 5. CardService has syncPendingTransactions method
 * 6. i18n translations include new strings
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readFile(relativePath) {
	return readFileSync(resolve(__dirname, '..', relativePath), 'utf-8');
}

describe('Settings sync/logout - Settings.vue template', () => {
	const source = readFile('pos/js/views/Settings.vue');

	it('shows pending queue items count', () => {
		expect(source).toContain("$t('Pending queue items:')");
		expect(source).toContain('pendingQueueCount');
	});

	it('has a sync now button', () => {
		expect(source).toContain("$t('Sync now')");
		expect(source).toContain('@click="syncNow"');
	});

	it('shows spinner on sync button while syncing', () => {
		expect(source).toContain('v-if="isSyncing"');
		expect(source).toContain(':disabled="isSyncing"');
	});

	it('shows sync error alert', () => {
		expect(source).toContain('v-if="syncError"');
		expect(source).toContain('variant="danger"');
		expect(source).toContain('syncError');
	});

	it('shows sync success alert', () => {
		expect(source).toContain('v-if="syncSuccess"');
		expect(source).toContain("$t('Synchronization complete.')");
	});

	it('shows spinner on logout button while logging out', () => {
		expect(source).toContain('v-if="isLoggingOut"');
		expect(source).toContain(':disabled="isLoggingOut"');
	});
});

describe('Settings sync/logout - Settings.vue data and methods', () => {
	const source = readFile('pos/js/views/Settings.vue');

	it('has pendingQueueCount data property', () => {
		expect(source).toContain('pendingQueueCount: 0');
	});

	it('has isSyncing data property', () => {
		expect(source).toContain('isSyncing: false');
	});

	it('has syncError data property', () => {
		expect(source).toContain('syncError: null');
	});

	it('has syncSuccess data property', () => {
		expect(source).toContain('syncSuccess: false');
	});

	it('has isLoggingOut data property', () => {
		expect(source).toContain('isLoggingOut: false');
	});

	it('imports AbstractOfflineQueue', () => {
		expect(source).toContain('AbstractOfflineQueue');
	});

	it('has syncNow method that calls uploadAllPending and syncPendingTransactions', () => {
		expect(source).toContain('async syncNow()');
		expect(source).toContain('AbstractOfflineQueue.uploadAllPending()');
		expect(source).toContain('syncPendingTransactions()');
	});

	it('refreshPendingTransactionCount also fetches queue count', () => {
		expect(source).toContain('AbstractOfflineQueue.getAllPendingCount()');
	});

	it('logout is async and attempts sync before confirming', () => {
		expect(source).toContain('async logout()');
		expect(source).toContain('AbstractOfflineQueue.uploadAllPending()');
	});

	it('logout warns about pending items and allows cancel', () => {
		expect(source).toContain('hasPending');
		expect(source).toContain('this.isLoggingOut = false');
	});
});

describe('Settings sync/logout - AbstractOfflineQueue static methods', () => {
	const source = readFile('shared/js/services/AbstractOfflineQueue.js');

	it('has static getAllPendingCount method', () => {
		expect(source).toContain('static async getAllPendingCount()');
	});

	it('getAllPendingCount iterates localForage', () => {
		expect(source).toContain('localForage.iterate');
	});

	it('has static uploadAllPending method', () => {
		expect(source).toContain('static async uploadAllPending()');
	});

	it('uploadAllPending dynamically imports OrderService', () => {
		expect(source).toContain("import('./OrderService')");
	});
});

describe('Settings sync/logout - CardService.syncPendingTransactions', () => {
	const source = readFile('shared/js/nfccards/CardService.ts');

	it('has syncPendingTransactions method', () => {
		expect(source).toContain('syncPendingTransactions');
	});

	it('syncPendingTransactions delegates to transactionStore.refresh', () => {
		expect(source).toContain('this.transactionStore.refresh()');
	});
});

describe('Settings sync/logout - i18n translations', () => {
	const en = readFile('shared/js/i18n/en.js');
	const nl = readFile('shared/js/i18n/nl.js');
	const de = readFile('shared/js/i18n/de.js');
	const fr = readFile('shared/js/i18n/fr.js');
	const es = readFile('shared/js/i18n/es.js');

	it('has "Pending queue items:" translation in all languages', () => {
		[en, nl, de, fr, es].forEach(lang => {
			expect(lang).toMatch(/Pending queue items/);
		});
	});

	it('has "Sync now" translation in all languages', () => {
		[en, nl, de, fr, es].forEach(lang => {
			expect(lang).toMatch(/Sync now/);
		});
	});

	it('has "Synchronization complete." translation in all languages', () => {
		[en, nl, de, fr, es].forEach(lang => {
			expect(lang).toMatch(/Synchronization complete/);
		});
	});

	it('has pending items logout warning translation in all languages', () => {
		[en, nl, de, fr, es].forEach(lang => {
			expect(lang).toMatch(/pending items that have not been uploaded/);
		});
	});
});
