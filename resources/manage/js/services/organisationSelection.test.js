import { describe, expect, it } from 'vitest';
import { selectOrganisation } from './organisationSelection';

describe('selectOrganisation', () => {
	const items = [
		{ id: 1, name: 'Personal' },
		{ id: 2, name: 'Team' },
	];

	it('returns the first organisation when nothing is stored', () => {
		expect(selectOrganisation(items, null)).toEqual(items[0]);
	});

	it('returns the stored organisation when present', () => {
		expect(selectOrganisation(items, '2')).toEqual(items[1]);
	});

	it('ignores a stale stored id', () => {
		expect(selectOrganisation(items, '999')).toEqual(items[0]);
	});

	it('returns null for an empty or missing list', () => {
		expect(selectOrganisation([], null)).toBeNull();
		expect(selectOrganisation(null, '1')).toBeNull();
	});
});
