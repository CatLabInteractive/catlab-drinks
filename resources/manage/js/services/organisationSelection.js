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
 * localStorage key holding the id of the organisation the user last
 * selected in the Manage app.
 */
export const ORGANISATION_STORAGE_KEY = 'catlab_drinks_manage_organisation_id';

/**
 * Pick the active organisation at boot: the stored selection when it is
 * still in the user's list, otherwise the first organisation.
 *
 * @param {Array|null} items organisations from GET /api/v1/users/me
 * @param {string|null} storedId value from localStorage
 * @returns {Object|null}
 */
export function selectOrganisation(items, storedId) {
	if (!items || items.length === 0) {
		return null;
	}

	if (storedId !== null && storedId !== undefined) {
		const stored = items.find((item) => String(item.id) === String(storedId));
		if (stored) {
			return stored;
		}
	}

	return items[0];
}
