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
				:disabled="copying || adding"
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
							:disabled="adding"
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
							:disabled="adding"
							@input="setCell(rowIndex, column, $event.target.value)"
							@paste="onPaste($event, rowIndex, column)"
						/>
					</td>
					<td class="text-right">
						<b-button size="sm" variant="danger" :disabled="adding" @click="deleteRow(rowIndex)" :title="$t('Delete')">
							<span>🗑️</span>
							<span class="sr-only">{{ $t('Delete') }}</span>
						</b-button>
					</td>
				</tr>
			</tbody>
		</table>

		<template #modal-footer>
			<div class="d-flex flex-wrap align-items-center w-100">
				<div class="d-flex align-items-center mr-3 mb-1">
					<label class="mb-0 mr-2" for="menu-import-category">{{ $t('Category for all products') }}</label>
					<select
						id="menu-import-category"
						class="form-control form-control-sm menu-import-category"
						v-model="categoryId"
						:disabled="creatingCategory || adding"
						@change="onSelectorChange('category', $event)"
					>
						<option :value="null"></option>
						<option v-for="category in categories" :key="category.id" :value="category.id">
							{{ category.name }}
						</option>
						<option v-if="createCategory" :value="NEW_CATEGORY">{{ $t('+ Create new category') }}</option>
					</select>
					<small class="text-danger ml-2 menu-import-category-message" v-if="categoryMessage">{{ categoryMessage }}</small>
				</div>

				<div class="d-flex align-items-center mr-3 mb-1">
					<label class="mb-0 mr-2" for="menu-import-heading">{{ $t('Heading for all products') }}</label>
					<select
						id="menu-import-heading"
						class="form-control form-control-sm menu-import-heading"
						v-model="headingId"
						:disabled="creatingHeading || adding"
						@change="onSelectorChange('heading', $event)"
					>
						<option :value="null"></option>
						<option v-for="heading in headings" :key="heading.id" :value="heading.id">
							{{ heading.name }}
						</option>
						<option v-if="createHeading" :value="NEW_HEADING">{{ $t('+ Create new heading') }}</option>
					</select>
					<small class="text-danger ml-2 menu-import-heading-message" v-if="headingMessage">{{ headingMessage }}</small>
				</div>

				<div class="ml-auto mb-1 text-right">
					<b-button v-if="hasErrors" variant="outline-danger" :disabled="adding" @click="deleteMarkedRows">
						{{ $t('Delete marked rows') }}
					</b-button>

					<b-button variant="light" class="ml-2" :disabled="adding" @click="hide">
						{{ $t('Cancel') }}
					</b-button>

					<b-button
						variant="primary"
						class="ml-2 menu-import-add"
						:disabled="!importable || copying || creatingCategory || creatingHeading || adding"
						@click="submit"
					>
						{{ $t('Add {count} products', { count: importRows.length }) }}
					</b-button>

					<div class="text-danger small mt-1 menu-import-add-message" v-if="addMessage">{{ addMessage }}</div>
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
		sheetFromItems,
		headingOrderFromItems,
		matchByName,
		namesToCreate
	} from "../menu/menuImport";
	import {rowFromImport} from "../menu/menuChanges";
	import {MenuService} from "../../../shared/js/services/MenuService";
	import {getApiErrorMessage} from "../../../shared/js/services/AbstractService";

	const TYPE_LABELS = {
		name: 'Product name',
		description: 'Description',
		price: 'Price',
		vat_percentage: 'VAT %',
		category: 'Station',
		heading: 'Heading',
		ignore: 'Ignore'
	};

	function isEmptyRow(row) {
		return row.every(cell => String(cell).trim() === '');
	}

	function emptyRow(width) {
		return new Array(width).fill('');
	}

	// Values of the selector options that create a category or a heading.
	// No category or heading has this ID.
	const NEW_CATEGORY = 'new';
	const NEW_HEADING = 'new';

	/**
	 * The two selectors "for all products" work the same. Each entry names
	 * the data fields, props and texts of one selector. `field` is the field
	 * of an import row that holds a name of this kind.
	 */
	const SELECTORS = {
		category: {
			field: 'category',
			value: 'categoryId',
			previous: 'previousCategoryId',
			creating: 'creatingCategory',
			message: 'categoryMessage',
			list: 'categories',
			create: 'createCategory',
			newValue: NEW_CATEGORY,
			prompt: vm => vm.$t('Enter the name of the new category:')
		},
		heading: {
			field: 'heading',
			value: 'headingId',
			previous: 'previousHeadingId',
			creating: 'creatingHeading',
			message: 'headingMessage',
			list: 'headings',
			create: 'createHeading',
			newValue: NEW_HEADING,
			prompt: vm => vm.$t('Enter the name of the new heading:')
		}
	};

	export default {
		props: {
			categories: {
				type: Array,
				default: () => []
			},

			/**
			 * The headings of the event: { id, name }.
			 */
			headings: {
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
			},

			/**
			 * Creates a category on the server and returns it: (name) => Promise<{ id, name }>.
			 * The parent adds it to `categories`. Without this function, the
			 * selector offers existing categories only.
			 */
			createCategory: {
				type: Function,
				default: null
			},

			/**
			 * Creates a heading on the server and returns it: (name) => Promise<{ id, name }>.
			 * The parent adds it to `headings`. Without this function, the
			 * selector offers existing headings only.
			 */
			createHeading: {
				type: Function,
				default: null
			}
		},

		emits: ['import'],

		data() {
			return {
				cells: [emptyRow(2)],
				types: ['name', 'price'],
				categoryId: null,
				// The category that was selected before the user chose "create new".
				previousCategoryId: null,
				creatingCategory: false,
				categoryMessage: null,
				headingId: null,
				// The heading that was selected before the user chose "create new".
				previousHeadingId: null,
				creatingHeading: false,
				headingMessage: null,
				NEW_CATEGORY: NEW_CATEGORY,
				NEW_HEADING: NEW_HEADING,
				// True while "Add" creates categories and headings.
				adding: false,
				addMessage: null,
				// Raised on each Add, show and close, so that a late answer is ignored.
				addRequest: 0,
				// The heading names of the copied event in its heading sequence.
				// New headings are created in this sequence.
				copiedHeadingOrder: [],
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
				this.previousCategoryId = null;
				this.creatingCategory = false;
				this.categoryMessage = null;
				this.headingId = null;
				this.previousHeadingId = null;
				this.creatingHeading = false;
				this.headingMessage = null;
				this.resetAdd();
				this.closeWithoutQuestion = false;
				this.resetCopy();
				this.$refs.modal.show();
			},

			/**
			 * "Create new category" and "Create new heading" ask for a name and
			 * create the entry at once, as the menu table does. On cancel or
			 * failure, the selector goes back to the entry that was selected
			 * before.
			 */
			async onSelectorChange(kind, event) {
				const selector = SELECTORS[kind];
				const select = event.target;
				this[selector.message] = null;

				if (this[selector.value] !== selector.newValue) {
					this[selector.previous] = this[selector.value];
					return;
				}

				const name = prompt(selector.prompt(this));
				if (!name || !name.trim()) {
					this.restoreSelection(selector, select);
					return;
				}

				this[selector.creating] = true;
				try {
					const entry = await this[selector.create](name.trim());
					this[selector.value] = entry.id;
					this[selector.previous] = entry.id;
				} catch (error) {
					this.restoreSelection(selector, select);
					this[selector.message] = getApiErrorMessage(error) || error.message;
				} finally {
					this[selector.creating] = false;
				}
			},

			/**
			 * Selects the previous entry again. The bound value can be the
			 * same before and after (no render in between), so Vue does not
			 * always update the element. Set the element too.
			 */
			restoreSelection(selector, select) {
				this[selector.value] = this[selector.previous];
				const index = this[selector.list].findIndex(entry => entry.id === this[selector.previous]);
				select.selectedIndex = index + 1;
			},

			hide() {
				this.$refs.modal.hide();
			},

			/**
			 * The header X and Cancel ask before a pasted sheet is thrown away.
			 * Backdrop and Esc do not close the dialog at all.
			 */
			onHide(bvModalEvent) {
				// Categories and headings are being created: wait for "Add".
				if (this.adding) {
					bvModalEvent.preventDefault();
					return;
				}
				if (this.closeWithoutQuestion) {
					this.closeWithoutQuestion = false;
					return;
				}
				if (this.contentRowIndexes.length > 0 && !confirm(this.$t('Discard the pasted menu?'))) {
					bvModalEvent.preventDefault();
				}
			},

			onHidden() {
				this.resetAdd();
				this.resetCopy();
			},

			resetAdd() {
				this.addRequest++;
				this.adding = false;
				this.addMessage = null;
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
				this.copiedHeadingOrder = headingOrderFromItems(menu.items);
			},

			/**
			 * Creates the categories and headings that the rows name and that
			 * do not exist yet, one at a time, then adds the rows. If a create
			 * fails, the dialog stays open and adds nothing. What was created
			 * stays, and the next "Add" finds it by name.
			 */
			async submit() {
				if (this.adding || !this.importable || this.copying || this.creatingCategory || this.creatingHeading
					|| this.categoryId === NEW_CATEGORY || this.headingId === NEW_HEADING) {
					return;
				}

				const request = ++this.addRequest;
				const importRows = this.importRows;
				this.adding = true;
				this.addMessage = null;

				let categories, headings;
				try {
					categories = await this.createMissing(importRows, SELECTORS.category, [], request);
					headings = await this.createMissing(importRows, SELECTORS.heading, this.copiedHeadingOrder, request);
				} catch (error) {
					if (request === this.addRequest) {
						this.adding = false;
						this.addMessage = getApiErrorMessage(error) || error.message;
					}
					return;
				}

				// The dialog was closed or opened again in the meantime.
				if (request !== this.addRequest) {
					return;
				}

				this.adding = false;
				this.$emit('import', importRows.map(row => rowFromImport(
					row,
					this.resolveId(row.category, categories, this.categoryId),
					this.resolveId(row.heading, headings, this.headingId)
				)));
				this.closeWithoutQuestion = true;
				this.hide();
			},

			/**
			 * Creates the missing entries of one kind and returns the list to
			 * match the rows against. Each name is matched against the current
			 * prop value first, because the parent adds each created entry.
			 */
			async createMissing(importRows, selector, preferredOrder, request) {
				const created = [];
				const current = () => this[selector.list].concat(created);
				const names = namesToCreate(importRows, selector.field, current(), preferredOrder);
				for (const name of names) {
					if (request !== this.addRequest || !this[selector.create]) {
						break;
					}
					if (!matchByName(name, current())) {
						created.push(await this[selector.create](name));
					}
				}
				return current();
			},

			/**
			 * A name gives the matching entry; an empty cell (or a name
			 * without a match) gives the selection for all products.
			 */
			resolveId(name, list, selectedId) {
				const match = matchByName(name, list);
				return match ? match.id : selectedId;
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
					// A new sheet: the heading sequence of a copied event no longer applies.
					this.copiedHeadingOrder = [];
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

	.menu-import-category,
	.menu-import-heading {
		width: auto;
		min-width: 12em;
	}
</style>
