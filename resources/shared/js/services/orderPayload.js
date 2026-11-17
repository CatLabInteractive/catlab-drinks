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
 * Builds the body that the API accepts for one order.
 *
 * The API links each order item to a product by its id. It rejects a link that
 * carries any other attribute (status 422, "Linkable resources may not contain
 * any other attributes."). The screens keep the name and the price of a product
 * on the item, for the confirm dialog and the receipt. This function makes a copy
 * for the upload and leaves the given order as it is. Pure function, no Vue.
 *
 * @param {object|null} order
 * @returns {object|null}
 */
export function toOrderPayload(order) {
	if (!order || !order.order || !Array.isArray(order.order.items)) {
		return order;
	}

	return {
		...order,
		order: {
			...order.order,
			items: order.order.items.map((item) => {
				if (!item || !item.menuItem) {
					return item;
				}
				return { ...item, menuItem: { id: item.menuItem.id } };
			}),
		},
	};
}
