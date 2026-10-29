/**
 * Tests to verify the POS round robin indicator:
 *
 * When several online POS terminals share remote orders, RemoteOrders.vue
 * must clearly show that orders are split between terminals.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readFile(relativePath) {
	return readFileSync(resolve(__dirname, '..', relativePath), 'utf-8');
}

describe('POS round robin indicator - RemoteOrders.vue', () => {
	const source = readFile('shared/js/components/RemoteOrders.vue');

	it('reads the active device count from the stranded orders summary', () => {
		expect(source).toContain('activeDevicesCount: 0');
		expect(source).toContain('response.active_devices');
	});

	it('only treats round robin as active on a POS with more than one active device', () => {
		expect(source).toContain('roundRobinActive()');
		expect(source).toContain('!!this.currentDeviceId && this.activeDevicesCount > 1');
	});

	it('shows a badge and an alert while round robin is active', () => {
		expect(source).toContain('<b-badge v-if="roundRobinActive"');
		expect(source).toContain('v-if="roundRobinActive && onlyAssignedOrders"');
		expect(source).toContain('v-if="roundRobinActive && !onlyAssignedOrders"');
		expect(source).toContain("$t('Round robin is active')");
	});
});
