/**
 * Tests for Card.vue topup UI gating (allow_topup capability flag).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

describe('Card.vue topup gating', () => {
	const source = readFileSync(resolve(__dirname, '..', 'shared', 'js', 'components', 'Card.vue'), 'utf-8');

	it('reads the allow_topup capability flag with the Manage-safe idiom', () => {
		expect(source).toContain('window.DEVICE_ALLOW_TOPUP !== false');
	});

	it('gates the card-mutating UI on allowTopup', () => {
		expect(source).toContain('v-if="allowTopup"');
	});
});

describe('CheckIn.vue', () => {
	const source = readFileSync(resolve(__dirname, '..', 'shared', 'js', 'views', 'CheckIn.vue'), 'utf-8');

	it('still mounts the card component', () => {
		expect(source).toContain('<card');
	});
});
