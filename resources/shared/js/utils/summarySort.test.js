import { describe, it, expect } from 'vitest';
import { nextSort, sortGroups } from './summarySort';

function group(name, sales, rank) {
	return { rank, menuItem: { name }, sales };
}

function sale(amount, price = 1) {
	return { amount, price, totalSales: amount * price };
}

function names(groups) {
	return groups.map(g => g.menuItem ? g.menuItem.name : g.visibleName);
}

describe('nextSort', () => {
	it('sorts text and rank ascending on a first click', () => {
		expect(nextSort(null, 'name')).toEqual({ key: 'name', direction: 'asc' });
		expect(nextSort(null, 'rank')).toEqual({ key: 'rank', direction: 'asc' });
	});

	it('sorts amount and total largest first on a first click', () => {
		expect(nextSort(null, 'amount')).toEqual({ key: 'amount', direction: 'desc' });
		expect(nextSort(null, 'total')).toEqual({ key: 'total', direction: 'desc' });
	});

	it('reverses on a second click', () => {
		expect(nextSort({ key: 'amount', direction: 'desc' }, 'amount')).toEqual({ key: 'amount', direction: 'asc' });
	});

	it('starts again on a different column', () => {
		expect(nextSort({ key: 'amount', direction: 'asc' }, 'name')).toEqual({ key: 'name', direction: 'asc' });
	});
});

describe('sortGroups', () => {
	it('returns the input unchanged without a sort', () => {
		const groups = [group('B', [sale(1)]), group('A', [sale(1)])];
		expect(sortGroups(groups, null, 'nl')).toBe(groups);
	});

	it('does not change a frozen input', () => {
		const groups = Object.freeze([group('B', [sale(1)]), group('A', [sale(1)])]);

		const sorted = sortGroups(groups, { key: 'name', direction: 'asc' }, 'nl');

		expect(names(sorted)).toEqual(['A', 'B']);
		expect(names(groups)).toEqual(['B', 'A']);
	});

	it('sorts a group by the sum of its rows', () => {
		const groups = [group('B', [sale(11)]), group('A', [sale(10, 2), sale(2, 1)])];

		expect(names(sortGroups(groups, { key: 'amount', direction: 'desc' }, 'nl'))).toEqual(['A', 'B']);
		expect(names(sortGroups(groups, { key: 'total', direction: 'asc' }, 'nl'))).toEqual(['B', 'A']);
	});

	it('sorts by the highest price of a group', () => {
		const groups = [group('A', [sale(1, 2), sale(1, 1)]), group('B', [sale(1, 1.5)])];

		expect(names(sortGroups(groups, { key: 'price', direction: 'asc' }, 'nl'))).toEqual(['B', 'A']);
	});

	it('puts groups without a rank last in both directions', () => {
		const groups = [
			group('Two', [sale(1)], 2),
			{ visibleName: 'Unknown / manual', sales: [sale(1)] },
			group('One', [sale(1)], 1)
		];

		expect(names(sortGroups(groups, { key: 'rank', direction: 'asc' }, 'nl'))).toEqual(['One', 'Two', 'Unknown / manual']);
		expect(names(sortGroups(groups, { key: 'rank', direction: 'desc' }, 'nl'))).toEqual(['Two', 'One', 'Unknown / manual']);
	});

	it('keeps the default order for equal values', () => {
		const groups = [group('C', [sale(5)]), group('A', [sale(5)]), group('B', [sale(5)])];

		expect(names(sortGroups(groups, { key: 'total', direction: 'asc' }, 'nl'))).toEqual(['C', 'A', 'B']);
		expect(names(sortGroups(groups, { key: 'total', direction: 'desc' }, 'nl'))).toEqual(['C', 'A', 'B']);
	});

	it('sorts names with the locale, ignoring case', () => {
		const groups = [group('élan', [sale(1)]), group('Alles', [sale(1)]), group('agent', [sale(1)])];

		expect(names(sortGroups(groups, { key: 'name', direction: 'asc' }, 'nl'))).toEqual(['agent', 'Alles', 'élan']);
	});

	it('sorts numbers in names by value', () => {
		const groups = [group('Team 10', [sale(1)]), group('Team 2', [sale(1)])];

		expect(names(sortGroups(groups, { key: 'name', direction: 'asc' }, 'nl'))).toEqual(['Team 2', 'Team 10']);
	});
});
