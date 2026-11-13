/*
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

import { describe, it, expect } from 'vitest';
import { orderTableRows } from './orderTableRows';

const beer = { id: 2, name: 'Bieren', position: 2 };
const soda = { id: 5, name: 'Frisdrank', position: 1 };

function menu() {
	return [
		{ id: 1, name: 'Pils', heading: beer },
		{ id: 2, name: 'Chips' },
		{ id: 3, name: 'Cola', heading: soda },
		{ id: 4, name: 'Duvel', heading: beer },
		{ id: 5, name: 'Water', heading: null },
		{ isTotals: true, name: 'Total' },
	];
}

function describeRows(rows) {
	return rows.map(row => {
		if (row.isHeading) {
			return 'title:' + row.name;
		}
		return row.isTotals ? 'totals' : row.name;
	});
}

describe('orderTableRows', () => {
	it('puts a title row before the first product of each heading, in heading sequence', () => {
		expect(describeRows(orderTableRows(menu()))).toEqual([
			'Chips', 'Water', 'title:Frisdrank', 'Cola', 'title:Bieren', 'Pils', 'Duvel', 'totals',
		]);
	});

	it('gives products without a heading no title row', () => {
		const rows = orderTableRows(menu());
		const firstTitle = rows.findIndex(row => row.isHeading);
		expect(rows.slice(0, firstTitle).map(row => row.name)).toEqual(['Chips', 'Water']);
		expect(rows.filter(row => row.isHeading)).toHaveLength(2);
	});

	it('keeps the totals row last', () => {
		const items = menu();
		// The totals row is last in the product list, but test that it stays last when it is not.
		const totals = items.pop();
		items.unshift(totals);
		const rows = orderTableRows(items);
		expect(rows[rows.length - 1]).toBe(totals);
		expect(rows.filter(row => row.isTotals)).toHaveLength(1);
	});

	it('returns the same product objects, not copies', () => {
		const items = menu();
		const rows = orderTableRows(items);
		items.forEach(item => expect(rows).toContain(item));
		expect(rows).toHaveLength(items.length + 2);
	});

	it('gives title rows no amount, price or details, and a distinct key', () => {
		const titles = orderTableRows(menu()).filter(row => row.isHeading);
		titles.forEach(title => {
			expect(title.amount).toBeUndefined();
			expect(title.price).toBeUndefined();
			expect(title.isTotals).toBeFalsy();
			expect(title._showDetails).toBeFalsy();
			expect(title.id).toBeUndefined();
		});
		expect(titles.map(title => title.heading)).toEqual([soda, beer]);
	});

	it('returns the product list itself if no product has a heading', () => {
		const items = [
			{ id: 1, name: 'Pils' },
			{ id: 2, name: 'Cola', heading: null },
			{ isTotals: true, name: 'Total' },
		];
		const rows = orderTableRows(items);
		expect(rows).toBe(items);
		expect(rows.some(row => row.isHeading)).toBe(false);
	});

	it('does not change the product list', () => {
		const items = menu();
		const before = items.slice();
		orderTableRows(items);
		expect(items).toEqual(before);
		items.forEach((item, index) => expect(item).toBe(before[index]));
	});

	it('handles an empty menu with only the totals row', () => {
		const items = [{ isTotals: true, name: 'Total' }];
		expect(orderTableRows(items)).toBe(items);
	});
});
