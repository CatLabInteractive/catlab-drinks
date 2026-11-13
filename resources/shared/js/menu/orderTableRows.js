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

import { groupMenuItems } from './groupMenuItems';

/**
 * Builds the rows that the client order table shows from its product list
 * (products plus the totals row). Products are grouped by heading, with a
 * title row ({ isHeading: true, heading, name }) before the first product of
 * each heading, and the totals row last. Title rows are for display only: the
 * product list stays the source for amounts, totals and the order.
 * If no product has a heading, the product list itself is returned.
 */
export function orderTableRows(items) {
	const products = items.filter(item => !item.isTotals);
	const groups = groupMenuItems(products);
	if (!groups.some(group => group.heading)) {
		return items;
	}

	const rows = [];
	groups.forEach(group => {
		if (group.heading) {
			rows.push({ isHeading: true, heading: group.heading, name: group.heading.name });
		}
		rows.push(...group.items);
	});
	return rows.concat(items.filter(item => item.isTotals));
}
