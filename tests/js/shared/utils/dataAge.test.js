import { describe, it, expect } from 'vitest';
import { STALE_AFTER_MS, isStale, describeAge, formatLoadTime } from '@resources/shared/js/utils/dataAge';

describe('isStale', () => {
	it('treats data that never loaded as stale', () => {
		expect(isStale(null, 1000)).toBe(true);
	});

	it('is not stale at exactly the limit', () => {
		expect(STALE_AFTER_MS).toBe(120000);
		expect(isStale(0, 120000)).toBe(false);
	});

	it('is stale after the limit', () => {
		expect(isStale(0, 120001)).toBe(true);
	});
});

describe('describeAge', () => {
	it('counts seconds below one minute', () => {
		expect(describeAge(0)).toEqual({ unit: 's', value: 0 });
		expect(describeAge(59999)).toEqual({ unit: 's', value: 59 });
	});

	it('counts whole minutes from one minute', () => {
		expect(describeAge(60000)).toEqual({ unit: 'min', value: 1 });
	});

	it('shows zero seconds for a clock that runs behind', () => {
		expect(describeAge(-500)).toEqual({ unit: 's', value: 0 });
	});
});

describe('formatLoadTime', () => {
	it('shows the date and the time with seconds', () => {
		const text = formatLoadTime(new Date(2026, 10, 13, 22, 31, 53).getTime(), 'nl');

		expect(text).toContain('13');
		expect(text).toContain('22:31:53');
	});
});
