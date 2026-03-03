/**
 * Tests to verify POS offline support integration:
 *
 * 1. POS App.vue shows an offline badge when the OfflineManager reports offline
 * 2. RemoteOrders.vue shows an offline warning
 * 3. POS app.js caches device data and handles offline boot
 * 4. CardService wires hasApiConnection to OfflineManager
 * 5. TransactionStore wires isOnline to OfflineManager
 * 6. Bootstrap.js handles network errors gracefully
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readFile(relativePath) {
	return readFileSync(resolve(__dirname, '..', relativePath), 'utf-8');
}

describe('POS offline support - App.vue', () => {
	const source = readFile('pos/js/views/App.vue');

	it('imports getOfflineManager', () => {
		expect(source).toContain('getOfflineManager');
		expect(source).toMatch(/import\s*\{[^}]*getOfflineManager[^}]*\}\s*from/);
	});

	it('has isOffline data property', () => {
		expect(source).toContain('isOffline: false');
	});

	it('shows an offline badge when isOffline is true', () => {
		expect(source).toContain('v-if="isOffline"');
		expect(source).toContain("variant=\"warning\"");
		expect(source).toContain("$t('Offline')");
	});

	it('listens to OfflineManager state changes in mounted', () => {
		expect(source).toContain('getOfflineManager()');
		expect(source).toContain('offlineManager.isOnline()');
	});
});

describe('POS offline support - RemoteOrders.vue', () => {
	const source = readFile('shared/js/components/RemoteOrders.vue');

	it('imports getOfflineManager', () => {
		expect(source).toContain('getOfflineManager');
		expect(source).toMatch(/import\s*\{[^}]*getOfflineManager[^}]*\}\s*from/);
	});

	it('has isOffline data property', () => {
		expect(source).toContain('isOffline: false');
	});

	it('shows an offline warning alert', () => {
		expect(source).toContain('v-if="isOffline"');
		expect(source).toContain("$t('Device is offline. Remote orders cannot be processed until the connection is restored.')");
	});

	it('cleans up offline listener on destroy', () => {
		expect(source).toContain('_offlineListener');
		expect(source).toContain('unbind()');
	});
});

describe('POS offline support - app.js', () => {
	const source = readFile('pos/js/app.js');

	it('imports getOfflineManager', () => {
		expect(source).toContain('getOfflineManager');
	});

	it('imports caching services', () => {
		expect(source).toContain('installCacheInterceptors');
		expect(source).toContain('cacheResponse');
		expect(source).toContain('getCachedResponse');
	});

	it('initializes OfflineManager and sets it on window', () => {
		expect(source).toContain('window.OFFLINE_MANAGER = offlineManager');
	});

	it('installs caching interceptors on global axios', () => {
		expect(source).toContain('installCacheInterceptors(window.axios, offlineManager)');
	});

	it('installs caching interceptors on card service axios', () => {
		expect(source).toContain('installCacheInterceptors(cardServiceAxios, offlineManager)');
	});

	it('caches device data for offline use', () => {
		expect(source).toContain("cacheResponse('/pos-api/v1/devices/current'");
	});

	it('provides clear error message when offline with no cache', () => {
		expect(source).toContain('Cannot start POS app: no device data available (offline and no cache)');
	});

	it('sets offline manager on card service', () => {
		expect(source).toContain('setOfflineManager(offlineManager)');
	});

	it('enables skipRefreshWhenBadInternetConnection', () => {
		expect(source).toContain('setSkipRefreshWhenBadInternetConnection(true)');
	});

	it('uses fetchAndCachePublicKeys for offline key caching', () => {
		expect(source).toContain('fetchAndCachePublicKeys');
	});
});

describe('POS offline support - CardService.ts', () => {
	const source = readFile('shared/js/nfccards/CardService.ts');

	it('has setOfflineManager method', () => {
		expect(source).toContain('setOfflineManager(offlineManager');
	});

	it('hasApiConnection checks offlineManager', () => {
		const hasApiBlock = source.substring(
			source.indexOf('public hasApiConnection()'),
			source.indexOf('public setOfflineManager')
		);
		expect(hasApiBlock).toContain('this.offlineManager');
		expect(hasApiBlock).toContain('isOnline()');
	});

	it('has fetchAndCachePublicKeys method', () => {
		expect(source).toContain('async fetchAndCachePublicKeys');
		expect(source).toContain('localForage.setItem');
		expect(source).toContain('localForage.getItem');
	});

	it('passes offlineManager to transactionStore', () => {
		expect(source).toContain('this.transactionStore.setOfflineManager');
	});
});

describe('POS offline support - TransactionStore.ts', () => {
	const source = readFile('shared/js/nfccards/store/TransactionStore.ts');

	it('has setOfflineManager method', () => {
		expect(source).toContain('setOfflineManager(offlineManager');
	});

	it('isOnline checks offlineManager when set', () => {
		const isOnlineBlock = source.substring(
			source.indexOf('public isOnline()'),
			source.indexOf('public setOfflineManager')
		);
		expect(isOnlineBlock).toContain('this.offlineManager');
		expect(isOnlineBlock).toContain('isOnline()');
	});
});

describe('POS offline support - bootstrap.js network error handling', () => {
	const source = readFile('pos/js/bootstrap.js');

	it('handles network errors gracefully (no error.response)', () => {
		expect(source).toContain('if (!error.response)');
		expect(source).toContain('return Promise.reject(error)');
	});
});

describe('POS offline support - AbstractService.js network error handling', () => {
	const source = readFile('shared/js/services/AbstractService.js');

	it('handles network errors gracefully (no error.response)', () => {
		expect(source).toContain('if (!error.response)');
	});

	it('installs caching interceptors when OFFLINE_MANAGER is available', () => {
		expect(source).toContain('window.OFFLINE_MANAGER');
		expect(source).toContain('installCacheInterceptors');
	});
});

describe('POS offline support - i18n translations', () => {
	const en = readFile('shared/js/i18n/en.js');
	const nl = readFile('shared/js/i18n/nl.js');
	const de = readFile('shared/js/i18n/de.js');
	const fr = readFile('shared/js/i18n/fr.js');
	const es = readFile('shared/js/i18n/es.js');

	it('has Offline translation in all languages', () => {
		[en, nl, de, fr, es].forEach(lang => {
			expect(lang).toMatch(/['"]Offline['"]/);
		});
	});

	it('has remote orders offline warning in all languages', () => {
		[en, nl, de, fr, es].forEach(lang => {
			expect(lang).toMatch(/Device is offline/);
		});
	});
});
