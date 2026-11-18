/**
 * Sorting of the row groups of the sales summary. A group is one product (with
 * one row per price) or one orderer name. Groups sort as one unit.
 */

/** Columns that sort largest first on the first click. */
const DESCENDING_FIRST = ['amount', 'total'];

/**
 * @param {{ key: string, direction: 'asc'|'desc' }|null} current
 * @param {'rank'|'name'|'amount'|'price'|'total'} key
 * @returns {{ key: string, direction: 'asc'|'desc' }}
 */
export function nextSort(current, key) {
	if (current && current.key === key) {
		return { key, direction: current.direction === 'asc' ? 'desc' : 'asc' };
	}
	return { key, direction: DESCENDING_FIRST.includes(key) ? 'desc' : 'asc' };
}

function sum(group, field) {
	return group.sales.reduce((total, sale) => total + sale[field], 0);
}

const VALUES = {
	rank: group => group.rank,
	name: group => group.menuItem ? group.menuItem.name : group.visibleName,
	amount: group => sum(group, 'amount'),
	total: group => sum(group, 'totalSales'),
	price: group => Math.max(...group.sales.map(sale => sale.price))
};

/**
 * @param {Array} groups
 * @param {{ key: string, direction: 'asc'|'desc' }|null} sort
 * @param {string} locale
 * @returns {Array} `groups` itself without a sort, else a new sorted array.
 */
export function sortGroups(groups, sort, locale) {
	if (!sort) {
		return groups;
	}

	const value = VALUES[sort.key];
	const sign = sort.direction === 'asc' ? 1 : -1;
	const collator = new Intl.Collator(locale, { sensitivity: 'base' });

	const compare = (a, b) => typeof a === 'string' ? collator.compare(a, b) : a - b;

	return groups
		.map((group, index) => ({ group, index, value: value(group) }))
		.sort((a, b) => {
			// Groups without a value (names without a rank) go last in both directions.
			const missing = (a.value === undefined) - (b.value === undefined);
			if (missing || a.value === undefined) {
				return missing || a.index - b.index;
			}
			return sign * compare(a.value, b.value) || a.index - b.index;
		})
		.map(entry => entry.group);
}
