/**
 * Tests for the warning of SummaryFreshness. The `vue-script-only` plugin in
 * vitest.config.js makes an import of a .vue file give the options of its
 * <script> block.
 */
import { describe, it, expect } from 'vitest';
import component from './SummaryFreshness.vue';

function stale(props) {
	return component.computed.stale.call({ now: 1000000, loading: false, loadedAt: null, ...props });
}

describe('SummaryFreshness warning', () => {
	it('does not warn while the first load runs', () => {
		expect(stale({ loading: true })).toBe(false);
	});

	it('warns when the first load failed', () => {
		expect(stale({ loading: false })).toBe(true);
	});

	it('warns when the data is more than 2 minutes old', () => {
		expect(stale({ loadedAt: 1000000 - 120001 })).toBe(true);
	});
});
