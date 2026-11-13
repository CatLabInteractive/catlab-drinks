<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>

	<b-modal ref="modal" size="xl" :title="$t('Import menu')" no-close-on-backdrop no-close-on-esc @hide="onHide" @hidden="onHidden">

		<div class="form-group menu-import-copy" v-if="otherEvents.length > 0">
			<label for="menu-import-copy-event">{{ $t('Copy from event') }}</label>
			<select
				id="menu-import-copy-event"
				class="form-control form-control-sm menu-import-copy-select"
				:value="copyEventId"
				:disabled="copying"
				@change="copyFromEvent($event.target)"
			>
				<option value=""></option>
				<option v-for="event in otherEvents" :key="event.id" :value="String(event.id)">
					{{ event.name }}
				</option>
			</select>
			<small class="form-text text-muted menu-import-copy-message" v-if="copyMessage">{{ copyMessage }}</small>
		</div>

		<p class="text-muted">{{ $t('Paste a table from an email, Word, or Excel into the first cell.') }}</p>

		<b-alert variant="warning" :show="hasErrors">
			{{ $t('Rows without a name or a readable price are marked. Fix or delete them.') }}
		</b-alert>

		<table class="table table-sm menu-import-table">
			<thead>
				<tr>
					<th v-for="(type, column) in types" :key="column">
						<select
							class="form-control form-control-sm"
							:value="type"
							:data-column="column"
							@change="changeType(column, $event.target.value)"
						>
							<option v-for="option in columnTypes" :key="option" :value="option">
								{{ $t(typeLabels[option]) }}
							</option>
						</select>
					</th>
					<th class="menu-import-delete-column"></th>
				</tr>
			</thead>

			<tbody>
				<tr
					v-for="(row, rowIndex) in cells"
					:key="rowIndex"
					:class="{ 'table-warning': isMarked(rowIndex) }"
					:data-row-index="rowIndex"
				>
					<td v-for="(cell, column) in row" :key="column">
						<input
							type="text"
							class="form-control form-control-sm"
							:class="{
								'is-invalid': hasCellError(rowIndex, column),
								'menu-import-ignored': types[column] === 'ignore'
							}"
							:value="cell"
							:data-column="column"
							@input="setCell(rowIndex, column, $event.target.value)"
							@paste="onPaste($event, rowIndex, column)"
						/>
					</td>
					<td class="text-right">
						<b-button size="sm" variant="danger" @click="deleteRow(rowIndex)" :title="$t('Delete')">
							<span>🗑️</span>
							<span class="sr-only">{{ $t('Delete') }}</span>
						</b-button>
					</td>
				</tr>
			</tbody>
		</table>

		<template #modal-footer>
			<div class="d-flex align-items-center w-100">
				<label class="mb-0 mr-2" for="menu-import-category">{{ $t('Category for all products') }}</label>
				<select id="menu-import-category" class="form-control form-control-sm menu-import-category" v-model="categoryId">
					<option :value="null"></option>
					<option v-for="category in categories" :key="category.id" :value="category.id">
						{{ category.name }}
					</option>
				</select>

				<div class="ml-auto">
					<b-button v-if="hasErrors" variant="outline-danger" @click="deleteMarkedRows">
						{{ $t('Delete marked rows') }}
					</b-button>

					<b-button variant="light" class="ml-2" @click="hide">
						{{ $t('Cancel') }}
					</b-button>

					<b-button variant="primary" class="ml-2" :disabled="!importable || copying" @click="submit">
						{{ $t('Add {count} products', { count: importRows.length }) }}
					</b-button>
				</div>
			</div>
		</template>

	</b-modal>

</template>

<script>

	import {parseClipboardTable} from "../menu/clipboardTable";
	import {
		COLUMN_TYPES,
		prepareSheet,
		guessColumnTypes,
		setColumnType,
		buildImportRows,
		canImport,
		sheetFromItems
	} from "../menu/menuImport";
	import {rowFromImport} from "../menu/menuChanges";
	import {MenuService} from "../../../shared/js/services/MenuService";
	import {getApiErrorMessage} from "../../../shared/js/services/AbstractService";

	const TYPE_LABELS = {
		name: 'Product name',
		description: 'Description',
		price: 'Price',
		vat_percentage: 'VAT %',
		ignore: 'Ignore'
	};

	function isEmptyRow(row) {
		return row.every(cell => String(cell).trim() === '');
	}

	function emptyRow(width) {
		return new Array(width).fill('');
	}

	export default {
		props: {
			categories: {
				type: Array,
				default: () => []
			},

			/**
			 * The events whose menu can be copied: { id, name }, without the
			 * event whose menu is open.
			 */
			otherEvents: {
				type: Array,
				default: () => []
			}
		},

		emits: ['import'],

		data() {
			return {
				cells: [emptyRow(2)],
				types: ['name', 'price'],
				categoryId: null,
				closeWithoutQuestion: false,
				copyEventId: '',
				copyMessage: null,
				copying: false,
				// Raised on each copy, show and close, so that a late answer is ignored.
				copyRequest: 0,
				columnTypes: COLUMN_TYPES,
				typeLabels: TYPE_LABELS
			};
		},

		computed: {

			/**
			 * Sheet row indexes of the rows that have content. Only these rows
			 * are imported; empty rows are never marked.
			 */
			contentRowIndexes() {
				const indexes = [];
				this.cells.forEach((row, index) => {
					if (!isEmptyRow(row)) {
						indexes.push(index);
					}
				});
				return indexes;
			},

			importRows() {
				return buildImportRows(this.contentRowIndexes.map(index => this.cells[index]), this.types);
			},

			/**
			 * Sheet row index => errors of its import row.
			 */
			errorsByRow() {
				const errors = {};
				this.contentRowIndexes.forEach((sheetIndex, importIndex) => {
					errors[sheetIndex] = this.importRows[importIndex].errors;
				});
				return errors;
			},

			hasErrors() {
				return this.importRows.some(row => row.errors.length > 0);
			},

			importable() {
				return canImport(this.types, this.importRows);
			}

		},

		methods: {

			show() {
				this.cells = [emptyRow(2)];
				this.types = ['name', 'price'];
				this.categoryId = null;
				this.closeWithoutQuestion = false;
				this.resetCopy();
				this.$refs.modal.show();
			},

			hide() {
				this.$refs.modal.hide();
			},

			/**
			 * The header X and Cancel ask before a pasted sheet is thrown away.
			 * Backdrop and Esc do not close the dialog at all.
			 */
			onHide(bvModalEvent) {
				if (this.closeWithoutQuestion) {
					this.closeWithoutQuestion = false;
					return;
				}
				if (this.contentRowIndexes.length > 0 && !confirm(this.$t('Discard the pasted menu?'))) {
					bvModalEvent.preventDefault();
				}
			},

			onHidden() {
				this.resetCopy();
			},

			resetCopy() {
				this.copyRequest++;
				this.copyEventId = '';
				this.copyMessage = null;
				this.copying = false;
			},

			/**
			 * Fill the sheet with the menu of a different event. The source
			 * event is only read.
			 */
			async copyFromEvent(select) {
				const eventId = select.value;
				this.copyEventId = eventId;
				this.copyMessage = null;
				if (eventId === '') {
					return;
				}

				if (this.contentRowIndexes.length > 0 && !confirm(this.$t('Replace the content of the sheet?'))) {
					// The bound value does not change in this tick, so set the element too.
					this.copyEventId = '';
					select.value = '';
					return;
				}

				const request = ++this.copyRequest;
				this.copying = true;

				let menu;
				try {
					menu = await new MenuService(eventId).index();
				} catch (error) {
					if (request === this.copyRequest) {
						this.copying = false;
						this.copyEventId = '';
						this.copyMessage = getApiErrorMessage(error) || error.message;
					}
					return;
				}

				// The dialog was closed or opened again, or a different event was selected.
				if (request !== this.copyRequest) {
					return;
				}

				this.copying = false;
				if (menu.items.length === 0) {
					this.copyMessage = this.$t('This event has no products.');
					return;
				}

				const sheet = sheetFromItems(menu.items);
				this.cells = sheet.cells;
				this.types = sheet.types;
			},

			submit() {
				if (!this.importable || this.copying) {
					return;
				}
				this.$emit('import', this.importRows.map(row => rowFromImport(row, this.categoryId)));
				this.closeWithoutQuestion = true;
				this.hide();
			},

			changeType(column, type) {
				this.types = setColumnType(this.types, column, type);
			},

			setCell(rowIndex, column, value) {
				this.cells[rowIndex][column] = value;
			},

			isMarked(rowIndex) {
				const errors = this.errorsByRow[rowIndex];
				return !!errors && errors.length > 0;
			},

			hasCellError(rowIndex, column) {
				const errors = this.errorsByRow[rowIndex] || [];
				return errors.includes(this.types[column]);
			},

			deleteRow(rowIndex) {
				this.setRows(this.cells.filter((row, index) => index !== rowIndex));
			},

			deleteMarkedRows() {
				this.setRows(this.cells.filter((row, index) => !this.isMarked(index)));
			},

			/**
			 * The sheet always keeps at least one row to paste into.
			 */
			setRows(rows) {
				this.cells = rows.length > 0 ? rows : [emptyRow(this.types.length)];
			},

			onPaste(event, rowIndex, column) {
				const clipboard = event.clipboardData;
				if (!clipboard) {
					return;
				}

				const parsed = parseClipboardTable(clipboard.getData('text/plain'));
				// A single cell: keep the default paste of the browser.
				if (parsed.length === 0 || (parsed.length === 1 && parsed[0].length === 1)) {
					return;
				}

				event.preventDefault();

				if (this.cells.every(isEmptyRow)) {
					const sheet = prepareSheet(parsed);
					this.cells = sheet;
					this.types = guessColumnTypes(sheet);
					return;
				}

				this.writeBlock(parsed, rowIndex, column);
			},

			/**
			 * Write a pasted block into the sheet from the given cell. Columns
			 * that are added get the type 'ignore'.
			 */
			writeBlock(block, rowIndex, column) {
				const blockWidth = block.reduce((max, row) => Math.max(max, row.length), 0);
				const width = Math.max(this.types.length, column + blockWidth);
				const types = this.types.concat(new Array(width - this.types.length).fill('ignore'));

				const cells = this.cells.map(row => row.concat(emptyRow(width - row.length)));
				while (cells.length < rowIndex + block.length) {
					cells.push(emptyRow(width));
				}

				block.forEach((values, i) => {
					values.forEach((value, j) => {
						cells[rowIndex + i][column + j] = value;
					});
				});

				this.types = types;
				this.cells = cells;
			}

		}
	}
</script>

<style scoped>
	.menu-import-table td {
		vertical-align: middle;
	}

	.menu-import-table .form-control-sm {
		min-width: 6em;
	}

	.menu-import-delete-column {
		width: 3em;
	}

	.menu-import-ignored {
		background-color: #e9ecef;
		color: #6c757d;
	}

	.menu-import-table tr.table-warning .form-control:not(.menu-import-ignored) {
		background-color: transparent;
	}

	.menu-import-copy-select {
		width: auto;
		min-width: 16em;
	}

	.menu-import-category {
		width: auto;
		min-width: 12em;
	}
</style>
