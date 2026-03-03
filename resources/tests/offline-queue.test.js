/**
 * Tests for AbstractOfflineQueue reconnect behavior.
 *
 * When the OfflineManager signals that the device has come back online,
 * the offline queue should immediately trigger an upload instead of waiting
 * for the next 30-second periodic timer.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import fs from 'fs';
import path from 'path';

describe('AbstractOfflineQueue', () => {

	let source;

	beforeEach(() => {
		source = fs.readFileSync(
			path.resolve(__dirname, '../shared/js/services/AbstractOfflineQueue.js'),
			'utf-8'
		);
	});

	it('listens to OFFLINE_MANAGER for online transitions in startPeriodicUpload', () => {
		expect(source).toContain('window.OFFLINE_MANAGER');
		expect(source).toContain('OFFLINE_MANAGER.on(');
	});

	it('triggers uploadQueue when the device comes back online', () => {
		// The listener should check for online=true and call uploadQueue
		expect(source).toContain('if (online && !this.uploading)');
		expect(source).toContain('this.uploadQueue()');
	});

	it('clears the existing timeout before triggering immediate upload', () => {
		// Should clear the pending 30s timer to avoid double uploads
		expect(source).toContain('clearTimeout(this.timeout)');
	});

	it('cleans up the offline binding in destroy()', () => {
		expect(source).toContain('this._offlineBinding.unbind()');
	});

	it('fixes the destroy() guard to check this.timeout instead of this.interval', () => {
		// The original code had a bug: it checked this.interval instead of this.timeout
		expect(source).toContain('if (this.timeout)');
		expect(source).not.toContain('if (this.interval)');
	});
});
