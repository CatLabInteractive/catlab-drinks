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
import { groupMenuItems } from './groupMenuItems';

const beer = { id: 2, name: 'Bieren', position: 2 };
const soda = { id: 5, name: 'Frisdrank', position: 1 };
const items = [
	{ id: 1, name: 'Pils', heading: beer },
	{ id: 2, name: 'Chips' },
	{ id: 3, name: 'Cola', heading: soda },
	{ id: 4, name: 'Duvel', heading: beer },
	{ id: 5, name: 'Water', heading: null },
];

describe('groupMenuItems', () => {
	it('puts products without a heading first, then headings by position', () => {
		expect(groupMenuItems(items).map(g => [g.heading && g.heading.name, g.items.map(i => i.name)])).toEqual([
			[null, ['Chips', 'Water']], ['Frisdrank', ['Cola']], ['Bieren', ['Pils', 'Duvel']],
		]);
	});
	it('gives one group without title if no product has a heading', () => {
		expect(groupMenuItems([{ id: 1, name: 'Pils' }])).toEqual([{ heading: null, items: [{ id: 1, name: 'Pils' }] }]);
	});
	it('leaves out an empty first group', () => {
		const groups = groupMenuItems([{ id: 1, name: 'Pils', heading: beer }]);
		expect(groups).toHaveLength(1);
		expect(groups[0].heading.name).toBe('Bieren');
	});
	it('gives no groups for no products', () => {
		expect(groupMenuItems([])).toEqual([]);
	});
	it('falls back to the heading id for equal positions', () => {
		const a = { id: 9, name: 'A', position: 1 };
		const b = { id: 3, name: 'B', position: 1 };
		const groups = groupMenuItems([{ id: 1, name: 'x', heading: a }, { id: 2, name: 'y', heading: b }]);
		expect(groups.map(g => g.heading.name)).toEqual(['B', 'A']);
	});
	it('returns the same item objects and does not mutate its argument', () => {
		const copy = items.slice();
		const groups = groupMenuItems(items);
		expect(items).toEqual(copy);
		expect(groups[2].items[0]).toBe(items[0]);
		expect(groups[0].items[0]).toBe(items[1]);
	});
});
