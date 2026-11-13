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
 * Runs what the Save button has to send, one request at a time. After every
 * request that succeeds, the result is written into copies of the originals
 * and the rows, so that a failure in the middle leaves a state from which a
 * second run sends only what is still open. The row of a request that
 * succeeded is rebuilt from the item that the server returned, so that row
 * and original agree and a later run does not send stale values (for
 * example an is_selling that a POS device changed in the meantime). Never
 * throws.
 */

import { buildSavePlan, rowFromItem } from './menuChanges';

/**
 * The row for an item that the server returned, with the key of the row it
 * replaces.
 */
function rowFromServer(item, row) {
	return { ...rowFromItem(item), key: row.key };
}

/**
 * New rows that the user typed and then deleted were never sent; they are
 * dropped once the run is done.
 */
function withoutDeletedNewRows(rows) {
	return rows.filter(row => !(row.deleted && row.id === null));
}

export async function runSave(service, originals, rows) {
	let currentOriginals = originals.map(item => ({ ...item }));
	let currentRows = rows.map(row => ({ ...row }));
	const plan = buildSavePlan(currentOriginals, currentRows);

	try {
		for (const update of plan.updates) {
			const item = await service.updateFields(update.id, update.changes);
			currentOriginals = currentOriginals.map(original =>
				original.id === update.id ? { position: original.position, ...item } : original
			);
			currentRows = currentRows.map(row => (row.id === update.id ? rowFromServer(item, row) : row));
		}

		if (plan.creates.length) {
			const created = await service.bulkCreate(plan.creates.map(create => create.data));
			plan.creates.forEach((create, i) => {
				currentRows = currentRows.map(row => (row.key === create.key ? rowFromServer(created[i], row) : row));
				// No position until the sequence request has succeeded.
				currentOriginals.push({ ...created[i], position: null });
			});
		}

		for (const id of plan.deletes) {
			await service.delete(id);
			currentRows = currentRows.filter(row => row.id !== id);
			currentOriginals = currentOriginals.filter(original => original.id !== id);
		}

		if (plan.sequenceChanged) {
			const ids = plan.sequenceKeys.map(key => currentRows.find(row => row.key === key).id);
			await service.saveSequence(ids);
			currentOriginals = currentOriginals.map(original => {
				const index = ids.indexOf(original.id);
				return index === -1 ? original : { ...original, position: index + 1 };
			});
		}
	} catch (error) {
		return { originals: currentOriginals, rows: currentRows, error };
	}

	return { originals: currentOriginals, rows: withoutDeletedNewRows(currentRows), error: null };
}
