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

	<b-container fluid>

		<h2>{{ $t('Menu') }}</h2>

		<div class="menu-toolbar d-flex align-items-center mb-2">
			<b-button variant="success" @click="save" :disabled="saving || creatingCategory || !loaded || !dirty">
				{{ $t('Save changes') }}
			</b-button>

			<b-button variant="light" class="ml-2" @click="reset" :disabled="saving || loading">
				{{ $t('Reset') }}
			</b-button>

			<b-button variant="outline-secondary" class="ml-2" :disabled="saving || !loaded" @click="$refs.importModal.show()">
				{{ $t('Import menu') }}
			</b-button>

			<span class="menu-save-status ml-3 text-muted">{{ statusText }}</span>
		</div>

		<b-alert variant="danger" :show="!!errorMessage" dismissible @dismissed="errorMessage = null">
			{{ errorMessage }}
		</b-alert>

		<div class="text-center" v-if="loading">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<table class="table table-sm menu-table" ref="table" v-if="loaded">
			<thead>
				<tr>
					<th class="menu-handle-column"></th>
					<th>{{ $t('Product name') }}</th>
					<th>{{ $t('Description') }}</th>
					<th class="menu-number-column">{{ $t('Price') }}</th>
					<th class="menu-number-column">{{ $t('VAT %') }}</th>
					<th>{{ $t('Category') }}</th>
					<th class="text-center">{{ $t('Status') }}</th>
					<th class="text-right">{{ $t('Delete') }}</th>
				</tr>
			</thead>

			<tbody>
				<tr
					v-for="(row, index) in rows"
					:key="row.key"
					:class="rowClass(row, index)"
					:data-row-index="index"
					@dragover="onDragOver($event, index)"
					@drop="onDrop($event, index)"
				>
					<td
						class="menu-handle"
						:draggable="isTrailing(index) ? 'false' : 'true'"
						:title="isTrailing(index) ? '' : $t('Drag to change the sequence')"
						@dragstart="onDragStart($event, index)"
						@dragend="endDrag"
					>
						<span v-if="!isTrailing(index)" aria-hidden="true">⠿</span>
					</td>

					<td>
						<input
							type="text"
							class="form-control form-control-sm"
							:class="{ 'is-invalid': hasError(row, 'name') }"
							v-model="row.name"
							:disabled="row.deleted || saving"
							:placeholder="isTrailing(index) ? $t('Type to add a product…') : ''"
							:aria-label="$t('Product name')"
							data-column="0"
							@keydown="onKeydown($event, index, 0)"
						/>
					</td>

					<td>
						<input
							type="text"
							class="form-control form-control-sm"
							v-model="row.description"
							:disabled="row.deleted || saving"
							:aria-label="$t('Description')"
							data-column="1"
							@keydown="onKeydown($event, index, 1)"
						/>
					</td>

					<td class="menu-number-column">
						<input
							type="text"
							inputmode="decimal"
							class="form-control form-control-sm text-right"
							:class="{ 'is-invalid': hasError(row, 'price') }"
							v-model="row.price"
							:disabled="row.deleted || saving"
							:aria-label="$t('Price')"
							data-column="2"
							@keydown="onKeydown($event, index, 2)"
						/>
					</td>

					<td class="menu-number-column">
						<input
							type="text"
							inputmode="decimal"
							class="form-control form-control-sm text-right"
							:class="{ 'is-invalid': hasError(row, 'vat_percentage') }"
							v-model="row.vat_percentage"
							:disabled="row.deleted || saving"
							:aria-label="$t('VAT %')"
							data-column="3"
							@keydown="onKeydown($event, index, 3)"
						/>
					</td>

					<td>
						<select
							class="form-control form-control-sm"
							:value="row.category_id === null ? '' : String(row.category_id)"
							:disabled="row.deleted || saving"
							:aria-label="$t('Category')"
							data-column="4"
							@change="changeCategory($event, row)"
							@keydown="onKeydown($event, index, 4)"
						>
							<option value=""></option>

							<option v-for="category in categories" :key="category.id" :value="String(category.id)">
								{{ category.name }}
							</option>

							<option value="new">{{ $t('+ Create new category') }}</option>
						</select>
					</td>

					<td class="text-center">
						<b-button
							size="sm"
							:variant="row.is_selling ? 'success' : 'danger'"
							:disabled="row.deleted || saving"
							@click="row.is_selling = !row.is_selling"
						>
							{{ row.is_selling ? $t('Selling') : $t('Not selling') }}
						</b-button>
					</td>

					<td class="text-right">
						<template v-if="!isTrailing(index)">
							<b-button v-if="row.deleted" size="sm" variant="light" :disabled="saving" @click="row.deleted = false" :title="$t('Undo delete')">
								<span>↶</span>
								<span class="sr-only">{{ $t('Undo delete') }}</span>
							</b-button>

							<b-button v-else size="sm" variant="danger" :disabled="saving" @click="row.deleted = true" :title="$t('Delete')">
								<span>🗑️</span>
								<span class="sr-only">{{ $t('Delete') }}</span>
							</b-button>
						</template>
					</td>
				</tr>
			</tbody>
		</table>

		<menu-import-modal ref="importModal" :categories="categories" :other-events="otherEvents" @import="appendRows" />

	</b-container>

</template>

<script>

	import {MenuService} from "../../../shared/js/services/MenuService";
	import {CategoryService} from "../../../shared/js/services/CategoryService";
	import {EventService} from "../../../shared/js/services/EventService";
	import {getApiErrorMessage} from "../../../shared/js/services/AbstractService";
	import {
		rowsFromItems,
		newRow,
		isBlank,
		rowStatuses,
		validateRows,
		moveRow,
		hasChanges
	} from "../menu/menuChanges";
	import {runSave} from "../menu/menuSave";
	import MenuImportModal from "../components/MenuImportModal.vue";

	/**
	 * The rows, with one blank row at the end for a new product.
	 */
	function withTrailingBlank(rows) {
		const last = rows[rows.length - 1];
		if (last && isBlank(last) && !last.deleted) {
			return rows;
		}
		return [...rows, newRow()];
	}

	const STATUS_CLASSES = {
		new: 'table-success',
		changed: 'table-warning',
		moved: 'table-warning',
		deleted: 'table-danger'
	};

	export default {
		components: {
			MenuImportModal
		},

		mounted() {

			this.onBeforeUnload = (event) => {
				if (this.dirty) {
					event.preventDefault();
					event.returnValue = '';
				}
			};
			window.addEventListener('beforeunload', this.onBeforeUnload);

			this.loadEvent(this.$route.params.id);
			this.loadEvents();

		},

		beforeUnmount() {
			window.removeEventListener('beforeunload', this.onBeforeUnload);
		},

		beforeRouteLeave(to, from) {
			return this.confirmLeave();
		},

		beforeRouteUpdate(to, from) {
			if (to.params.id !== from.params.id) {
				return this.confirmLeave();
			}
		},

		watch: {
			'$route' (to, from) {
				// A different event reuses this view; load its menu.
				if (to.params.id !== from.params.id) {
					this.loadEvent(to.params.id);
				}
			},

			rows: {
				deep: true,
				handler(rows) {
					const completed = withTrailingBlank(rows);
					if (completed !== rows) {
						this.rows = completed;
					}
				}
			}
		},

		data() {
			return {
				loaded: false,
				loading: false,
				eventId: null,
				saving: false,
				creatingCategory: false,
				showErrors: false,
				errorMessage: null,
				originals: [],
				rows: [],
				categories: [],
				events: [],
				dragIndex: null,
				dropTarget: null
			}
		},

		computed: {

			dirty() {
				return hasChanges(this.originals, this.rows);
			},

			invalid() {
				return validateRows(this.rows);
			},

			invalidFields() {
				const fields = {};
				this.invalid.forEach(problem => {
					fields[problem.key] = problem.fields;
				});
				return fields;
			},

			/**
			 * The events whose menu the import can copy.
			 */
			otherEvents() {
				return this.events
					.filter(event => String(event.id) !== String(this.eventId))
					.map(event => ({ id: event.id, name: event.name }));
			},

			statuses() {
				return rowStatuses(this.originals, this.rows);
			},

			statusText() {
				if (this.saving) {
					return this.$t('Saving');
				}
				return this.dirty ? this.$t('Unsaved changes') : this.$t('All changes saved');
			}

		},

		methods: {

			loadEvent(eventId) {
				this.eventId = eventId;
				this.service = new MenuService(eventId);
				this.categoriesService = new CategoryService(eventId);
				// Hide the menu of the previous event until the new one is loaded,
				// so that Save cannot send it to the new event.
				this.loaded = false;
				this.originals = [];
				this.rows = [];
				this.showErrors = false;
				this.errorMessage = null;
				this.refresh();
			},

			/**
			 * The events of the organisation, in the sequence of the events page.
			 * Without this list the import only hides its copy selector.
			 */
			async loadEvents() {
				try {
					this.events = (await new EventService(window.ORGANISATION_ID).index({sort: '!id'})).items;
				} catch (error) {
					this.events = [];
				}
			},

			async refresh() {

				const eventId = this.eventId;
				// The table is not editable while it reloads.
				this.loaded = false;
				this.loading = true;

				let menu, categories;
				try {
					[menu, categories] = await Promise.all([
						this.service.index(),
						this.categoriesService.index()
					]);
				} catch (error) {
					if (eventId === this.eventId) {
						// Reset stays available to try again.
						this.loading = false;
						this.errorMessage = getApiErrorMessage(error) || error.message;
					}
					return;
				}

				// The user switched to a different event in the meantime.
				if (eventId !== this.eventId) {
					return;
				}

				this.originals = menu.items;
				this.categories = categories.items;
				this.rows = withTrailingBlank(rowsFromItems(this.originals));
				this.loading = false;
				this.loaded = true;

			},

			async save() {

				this.showErrors = true;
				if (this.invalid.length > 0) {
					return;
				}

				this.saving = true;
				this.errorMessage = null;

				const eventId = this.eventId;
				const result = await runSave(this.service, this.originals, this.rows);

				// The user switched to a different event in the meantime.
				if (eventId !== this.eventId) {
					this.saving = false;
					return;
				}

				this.originals = result.originals;
				this.rows = withTrailingBlank(result.rows);
				this.saving = false;

				if (result.error) {
					this.errorMessage = getApiErrorMessage(result.error)
						|| this.$t('Some changes were not saved. Press Save to try again.');
				} else {
					this.showErrors = false;
				}

			},

			async reset() {

				if (this.dirty && !confirm(this.$t('Discard all unsaved changes?'))) {
					return;
				}

				this.showErrors = false;
				this.errorMessage = null;
				await this.refresh();

			},

			confirmLeave() {
				if (this.dirty && !confirm(this.$t('You have unsaved changes. Leave this page?'))) {
					return false;
				}
				return true;
			},

			/**
			 * Add rows (from the import) before the trailing blank row.
			 */
			appendRows(rows) {
				const at = this.rows.length - 1;
				this.rows = [...this.rows.slice(0, at), ...rows, ...this.rows.slice(at)];
			},

			isTrailing(index) {
				return index === this.rows.length - 1;
			},

			hasError(row, field) {
				return this.showErrors && (this.invalidFields[row.key] || []).includes(field);
			},

			rowClass(row, index) {
				const classes = [];
				const statusClass = STATUS_CLASSES[this.statuses[row.key]];
				if (statusClass) {
					classes.push(statusClass);
				}
				if (row.deleted) {
					classes.push('menu-row-deleted');
				}
				if (this.dropTarget && this.dropTarget.index === index) {
					classes.push(this.dropTarget.after ? 'menu-drop-after' : 'menu-drop-before');
				}
				return classes;
			},

			async changeCategory(event, row) {
				const value = event.target.value;

				if (value === 'new') {
					const name = prompt(this.$t('Enter the name of the new category:'));
					if (!name) {
						// Cancelled: show the previous category again.
						event.target.value = row.category_id === null ? '' : String(row.category_id);
						return;
					}

					this.creatingCategory = true;
					let category;
					try {
						category = await this.categoriesService.create({name: name});
					} catch (error) {
						event.target.value = row.category_id === null ? '' : String(row.category_id);
						this.errorMessage = getApiErrorMessage(error) || error.message;
						return;
					} finally {
						this.creatingCategory = false;
					}

					this.categories.push(category);
					row.category_id = category.id;
					event.target.value = String(category.id);
				} else if (value) {
					const category = this.categories.find(candidate => String(candidate.id) === value);
					row.category_id = category ? category.id : null;
				} else {
					row.category_id = null;
				}
			},

			onDragStart(event, index) {
				if (this.isTrailing(index) || this.saving) {
					event.preventDefault();
					return;
				}

				this.dragIndex = index;
				event.dataTransfer.effectAllowed = 'move';
				// Firefox only starts a drag that carries data.
				event.dataTransfer.setData('text/plain', String(index));

				const tr = event.target.closest('tr');
				if (tr) {
					event.dataTransfer.setDragImage(tr, 0, tr.offsetHeight / 2);
				}
			},

			onDragOver(event, index) {
				if (this.dragIndex === null) {
					return;
				}

				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';

				const rect = event.currentTarget.getBoundingClientRect();
				// Nothing goes after the trailing blank row.
				const after = !this.isTrailing(index) && event.clientY > rect.top + rect.height / 2;

				if (!this.dropTarget || this.dropTarget.index !== index || this.dropTarget.after !== after) {
					this.dropTarget = { index, after };
				}
			},

			onDrop(event, index) {
				if (this.dragIndex === null) {
					return;
				}

				event.preventDefault();

				const after = this.dropTarget && this.dropTarget.index === index && this.dropTarget.after;
				// Position in the current list in front of which the row goes.
				const before = Math.min(after ? index + 1 : index, this.rows.length - 1);
				const to = before > this.dragIndex ? before - 1 : before;

				if (to !== this.dragIndex) {
					this.rows = moveRow(this.rows, this.dragIndex, to);
				}

				this.endDrag();
			},

			endDrag() {
				this.dragIndex = null;
				this.dropTarget = null;
			},

			onKeydown(event, index, column) {
				const isSelect = event.target.tagName === 'SELECT';

				if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
					event.preventDefault();
					this.moveByKeyboard(index, event.key === 'ArrowUp' ? -1 : 1, column);
					return;
				}

				if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
					return;
				}

				// Plain arrows change the value of a select; leave them alone there.
				if (event.key === 'Enter' || (event.key === 'ArrowDown' && !isSelect)) {
					event.preventDefault();
					this.focusCell(index, 1, column);
				} else if (event.key === 'ArrowUp' && !isSelect) {
					event.preventDefault();
					this.focusCell(index, -1, column);
				}
			},

			moveByKeyboard(index, delta, column) {
				const to = index + delta;
				// The trailing blank row stays last.
				if (this.isTrailing(index) || to < 0 || to >= this.rows.length - 1) {
					return;
				}

				this.rows = moveRow(this.rows, index, to);
				this.$nextTick(() => this.focusAt(to, column));
			},

			/**
			 * Focus the same column in the nearest row in the given direction
			 * whose cell can take focus (deleted rows cannot).
			 */
			focusCell(index, direction, column) {
				for (let i = index + direction; i >= 0 && i < this.rows.length; i += direction) {
					if (this.focusAt(i, column)) {
						return;
					}
				}
			},

			focusAt(index, column) {
				const table = this.$refs.table;
				if (!table) {
					return false;
				}

				const cell = table.querySelector('tr[data-row-index="' + index + '"] [data-column="' + column + '"]');
				if (!cell || cell.disabled) {
					return false;
				}

				cell.focus();
				if (typeof cell.select === 'function' && cell.tagName === 'INPUT') {
					cell.select();
				}
				return true;
			}
		}
	}
</script>

<style scoped>
	.menu-table td {
		vertical-align: middle;
	}

	.menu-table .form-control-sm {
		min-width: 4em;
	}

	.menu-handle-column {
		width: 2em;
	}

	.menu-number-column {
		width: 7em;
	}

	.menu-handle {
		cursor: grab;
		text-align: center;
		color: #6c757d;
		user-select: none;
	}

	/* Let the row colour show through the cells of a marked row. */
	.menu-table tr.table-success .form-control,
	.menu-table tr.table-warning .form-control,
	.menu-table tr.table-danger .form-control {
		background-color: transparent;
	}

	.menu-row-deleted input,
	.menu-row-deleted select {
		text-decoration: line-through;
		opacity: 0.6;
	}

	.menu-drop-before > td {
		box-shadow: inset 0 2px 0 #007bff;
	}

	.menu-drop-after > td {
		box-shadow: inset 0 -2px 0 #007bff;
	}
</style>
