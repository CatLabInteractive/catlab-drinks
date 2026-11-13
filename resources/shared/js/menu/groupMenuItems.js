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

/**
 * Groups the products of a menu by heading, for the order screens. The first
 * group holds the products without a heading (left out if empty), the other
 * groups follow in heading position, then heading id order. An item without
 * a heading key (for example from a cached menu) counts as no heading.
 */
export function groupMenuItems(items) {
	const loose = [];
	const byHeading = new Map();
	items.forEach(item => {
		if (!item.heading) {
			loose.push(item);
			return;
		}
		if (!byHeading.has(item.heading.id)) {
			byHeading.set(item.heading.id, { heading: item.heading, items: [] });
		}
		byHeading.get(item.heading.id).items.push(item);
	});

	const headed = Array.from(byHeading.values()).sort((a, b) =>
		(a.heading.position - b.heading.position) || (a.heading.id - b.heading.id)
	);
	return loose.length ? [{ heading: null, items: loose }, ...headed] : headed;
}
