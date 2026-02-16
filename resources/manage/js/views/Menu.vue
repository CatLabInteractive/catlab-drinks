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

		<h2>
			Menu

			<b-button size="sm" class="btn-success" @click="createNew" title="Create new menu item">
				<i class="fas fa-plus"></i>
				<span class="sr-only">Create new menu item</span>
			</b-button>
		</h2>

		<div class="text-center" v-if="!loaded">
			<b-spinner label="Loading data" />
		</div>

		<b-row>
			<b-col>
				<b-table striped hover :items="items" :fields="fields" v-if="loaded">

					<template v-slot:cell(actions)="row">
						<b-button size="sm" class="" @click="edit(row.item, row.index)">
							<i class="fas fa-edit"></i>
							<span class="sr-only">Edit</span>
						</b-button>
						<b-button size="sm" @click="remove(row.item)" class="btn-danger">
							<i class="fas fa-trash"></i>
							<span class="sr-only">Delete</span>
						</b-button>
					</template>

					<template v-slot:cell(is_selling)="row">
						<b-button v-if="!row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)" class="btn-danger">
							Not selling
						</b-button>

						<b-button v-if="row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)" class="btn-success">
							Selling
						</b-button>

						<b-spinner v-if="toggling === row.item.id" small></b-spinner>
					</template>

					<template v-slot:cell(price)="row">
						{{ row.item.price.toFixed(2) }}
					</template>

					<template v-slot:cell(category)="row">
						{{ row.item.category?.name }}
					</template>

					<template v-slot:cell(vat_percentage)="row">
						<span v-if="row.item.vat_percentage">
							{{ row.item.vat_percentage.toFixed(2) }}%
						</span>
					</template>

				</b-table>
			</b-col>
		</b-row>

	</b-container>

	<b-modal ref="editFormModal" :title="(model.id ? 'Edit item ID#' + model.id : 'New item')" @hide="resetForm">
		<form @submit.prevent="save">
			<b-form-group label="Name">
				<b-form-input type="text" v-model="model.name"></b-form-input>
			</b-form-group>

			<b-form-group label="Description">
				<b-form-input type="text" v-model="model.description"></b-form-input>
			</b-form-group>

			<b-form-group label="Price (all taxes included)">
				<b-form-input type="number" v-model="model.price" step=".01"></b-form-input>
			</b-form-group>

			<b-form-group label="VAT / Tax percentage (optional)">
				<b-form-input type="number" v-model="model.vat_percentage" step=".01"></b-form-input>
			</b-form-group>

			<b-form-group label="Product category" description="By defining multiple product categories, you can split your orders between different locations. Categories should be broad: ie 'Food' for kitchen and 'Drinks' for bar.">
				<select @change="changeCategory($event, model)" v-model="categoryId" class="full-width form-control">
					<option></option>

					<option v-for="category in categories" :value="category.id">
						{{ category.name }}
					</option>

					<option value="new">+ Create new category</option>
				</select>
			</b-form-group>
		</form>

		<template #modal-footer>
			<b-btn type="button" variant="light" @click="resetForm()">Reset</b-btn>
			<b-btn type="submit" variant="success" @click="save" :disabled="saving">Save</b-btn>

			<b-alert v-if="saving" variant="none" show>Saving</b-alert>
		</template>
	</b-modal>

</template>

<script>

	import {MenuService} from "../../../shared/js/services/MenuService";
	import {CategoryService} from "../../../shared/js/services/CategoryService";

	export default {
		mounted() {

			this.eventId = this.$route.params.id;
			this.service = new MenuService(this.eventId);
			this.categoriesService = new CategoryService(this.eventId);
			this.refresh();

		},

		watch: {
			'$route' (to, from) {
				// react to route changes...
				this.eventId = to.params.id;

				this.service = new MenuService(this.eventId);
				this.categoriesService = new CategoryService(this.eventId);
				this.refresh();
			}
		},

		data() {
			return {
				loaded: false,
				eventId: null,
				saving: false,
				saved: false,
				toggling: null,
				items: [],
				categories: [],
				categoryId: null,
				fields: [
					{
						key: 'name',
						label: 'Product name',
					},
					{
						key: 'price',
						label: 'Price',
						class: 'text-center'
					},
					{
						key: 'vat_percentage',
						label: 'VAT %',
						class: 'text-center'
					},
					{
						key: 'is_selling',
						label: 'Status',
						class: 'text-center'
					},
					{
						key: 'category',
						label: 'Category',
						class: 'text-center'
					},
					{
						key: 'actions',
						label: 'Actions',
						class: 'text-right'
					}
				],
				model: {}
			}
		},

		methods: {

			async refresh() {

				this.items = (await this.service.index()).items;
				this.categoryId = null;
				this.categories = (await this.categoriesService.index()).items;
				this.loaded = true;

			},

			validate() {
				if (!this.model.name) {
					alert('Please enter a name for the product.');
					return false;
				}

				console.log('Price', this.model.price);
				if (typeof(this.model.price) === 'undefined') {
					alert('Please enter a price for the product.');
					return false;
				}

				return true;
			},

			async save(e) {

				if (!this.validate()) {
					return;
				}

				this.saving = true;
				if (this.model.id) {
					await this.service.update(this.model.id, this.model);
				} else {
					await this.service.create(this.model);
				}

				this.model = {};
				this.saving = false;
				this.$refs.editFormModal.hide();

				this.refresh();

			},

			async edit(model, index) {

				this.$refs.editFormModal.show();

				this.model = Object.assign({}, model);
				this.categoryId = this.model.category ? this.model.category.id : null;

			},

			async remove(model) {

				if (confirm('Are you sure you want to remove this menu item?')) {
					if (this.model.id === model.id) {
						this.model = {};
					}

					await this.service.delete(model.id);
					await this.refresh();
				}

			},

			async toggleIsSelling(model) {

				this.toggling = model.id;
				model.is_selling = !model.is_selling;
				await this.service.update(model.id, model);
				this.toggling = null;

			},

			async changeCategory(e, model) {
				const value = e.target.value;

				if (value === 'new') {
					model.category = null;

					let newCategory = prompt('Enter the name of the new category:');
					if (newCategory) {
						let category = await this.categoriesService.create({name: newCategory});
						this.categories.push(category);

						this.categoryId = category.id;
						model.category = {
							id: category.id
						};
					}
				} else if(value) {
					model.category = {
						id: value
					};
				} else {
					model.category = null;
				}
			},

			resetForm() {
				this.$refs.editFormModal.hide();
				this.model = {};
				this.categoryId = null;
			},

			createNew() {
				this.$refs.editFormModal.show();
			}
		}
	}
</script>
