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
 * Splits text pasted from a spreadsheet or a text editor into rows and
 * cells. Cells are separated by tabs, rows by line breaks. A cell that
 * starts with a double quote is quoted: it may contain tabs and line
 * breaks, and a doubled quote stands for one quote. Every row has the same
 * number of cells, and rows without any content are dropped.
 */
export function parseClipboardTable(text) {
	if (typeof text !== 'string' || text === '') {
		return [];
	}

	const rows = [];
	let row = [];
	let cell = '';
	let i = 0;
	const n = text.length;

	const endCell = () => {
		row.push(cell.trim());
		cell = '';
	};
	const endRow = () => {
		endCell();
		if (row.some((value) => value !== '')) {
			rows.push(row);
		}
		row = [];
	};

	while (i <= n) {
		if (i === n) {
			endRow();
			break;
		}
		const char = text[i];
		if (char === '"' && cell === '') {
			// Quoted cell.
			i++;
			while (i < n) {
				if (text[i] === '"') {
					if (text[i + 1] === '"') {
						cell += '"';
						i += 2;
						continue;
					}
					i++;
					break;
				}
				if (text[i] === '\r' && text[i + 1] === '\n') {
					cell += ' ';
					i += 2;
					continue;
				}
				cell += (text[i] === '\n' || text[i] === '\r') ? ' ' : text[i];
				i++;
			}
			continue;
		}
		if (char === '\t') {
			endCell();
		} else if (char === '\r' || char === '\n') {
			if (char === '\r' && text[i + 1] === '\n') {
				i++;
			}
			endRow();
		} else {
			cell += char;
		}
		i++;
	}

	const width = rows.reduce((max, r) => Math.max(max, r.length), 0);
	return rows.map((r) => r.concat(Array(width - r.length).fill('')));
}
