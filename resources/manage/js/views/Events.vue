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

		<h1>
			Events

			<b-button size="sm" class="btn-success" @click="createNew" title="Create new event">
				<span>＋</span>
				<span class="sr-only">Create new event</span>
			</b-button>
		</h1>
		<div class="text-center" v-if="!loaded">
			<b-spinner label="Loading data"/>
		</div>

		<b-row>
			<b-col>
				<b-table striped hover :items="items" :fields="fields" v-if="loaded">

					<template v-slot:cell(name)="row">
						{{ row.item.name }}
					</template>

					<template v-slot:cell(order_token)="row">
						<!--
						<a :href="row.item.order_url" target="_blank" title="Client panel">
							<pre>{{ row.item.order_token }}></pre>
						</a>
						-->
						<input @click="selectOrderToken($event)" :value="row.item.order_token" class="order-token"
							   readonly></input>
					</template>

					<template v-slot:cell(actions)="row">

						<b-dropdown text="Actions" size="sm" right>

							<b-dropdown-item class="" @click="edit(row.item, row.index)" title="Edit">
								✏️
								Edit
							</b-dropdown-item>

							<b-dropdown-item @click="remove(row.item)" title="Remove">
								🗑️
								Delete
							</b-dropdown-item>

							<b-dropdown-divider></b-dropdown-divider>

							<b-dropdown-group header="Sales">

								<b-dropdown-item :to="{ name: 'menu', params: { id: row.item.id } }" title="Edit menu">
									📜
									Edit menu
								</b-dropdown-item>

								<b-dropdown-item :to="{ name: 'summary', params: { id: row.item.id } }"
												 title="Sales overview">
									📊
									Sales overview
								</b-dropdown-item>

								<b-dropdown-item :href="row.item.order_url" target="_blank" title="Client panel">
									👤
									Client order form
								</b-dropdown-item>


								<b-dropdown-item :to="{ name: 'sales', params: { id: row.item.id } }">
									📋
									Order history
								</b-dropdown-item>

							</b-dropdown-group>

							<b-dropdown-divider></b-dropdown-divider>

							<b-dropdown-group header="Attendees">
								<b-dropdown-item :to="{ name: 'attendees', params: { id: row.item.id } }"
												 title="Attendees">
									👥
									Register attendees
								</b-dropdown-item>

								<b-dropdown-item :to="{ name: 'checkIn', params: { id: row.item.id } }"
												 title="Check-In">
									🛂
									Check-In attendees
								</b-dropdown-item>
							</b-dropdown-group>

						</b-dropdown>


					</template>

					<template v-slot:cell(is_selling)="row">

						<b-button v-if="!row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)"
								  class="btn-danger">
							Closed
						</b-button>

						<b-button v-if="row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)"
								  class="btn-success">
							Open
						</b-button>

						<b-spinner v-if="toggling === row.item.id" small></b-spinner>
					</template>

				</b-table>
			</b-col>
		</b-row>

		<b-row class="mt-4">
			<b-col>
				<b-card>
					<h5><span class="mr-1">📱</span> POS Device Pairing</h5>
					<p class="text-muted">
						POS (Point of Sale) devices authenticate separately from your management account.
						Each POS terminal requires its own pairing to ensure secure, independent operation—even
						if the management session expires, POS devices continue to function.
					</p>
					<b-button variant="primary" :to="{ name: 'devices' }">
						<span class="mr-1">🔗</span> Manage &amp; Pair Devices
					</b-button>
				</b-card>
			</b-col>
		</b-row>

	</b-container>

	<b-modal :title="(model.id ? 'Edit event ID#' + model.id : 'New event')" @hide="resetForm" ref="editFormModal">
		<form @submit.prevent="save">
			<b-form-group label="Name">
				<b-form-input type="text" v-model="model.name"></b-form-input>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="model.payment_cards">
					Allow payment with NFC topup cards<br/>
				</label>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="model.payment_cash">
					Allow payment in cash
				</label>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="model.payment_vouchers">
					Allow payment with vouchers
				</label>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="model.allow_unpaid_online_orders">
					Allow unpaid online orders (without providing card alias)
				</label>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="model.split_orders_by_categories">
					Split orders by product categories (e.g drinks for the bar, food for the kitchen)
				</label>
			</b-form-group>

			<b-form-group label="Voucher value">
				<b-form-input type="number" v-model="model.payment_voucher_value" step="0.01"></b-form-input>
			</b-form-group>

			<b-form-group label="Checkin URL (callback)">
				<b-form-input type="text" v-model="model.checkin_url"></b-form-input>
			</b-form-group>
		</form>

		<template #modal-footer>
			<b-btn type="button" variant="light" @click="resetForm()">Reset</b-btn>
			<b-btn type="submit" variant="success" @click="save()">Save</b-btn>

			<b-alert v-if="saving" variant="none" show>Saving</b-alert>
		</template>
	</b-modal>

</template>

<script>

import {EventService} from "../../../shared/js/services/EventService";

export default {
	mounted() {

		this.service = new EventService(window.ORGANISATION_ID); // hacky hacky
		this.resetForm();
		this.refreshEvents();

	},

	data() {
		return {
			loaded: false,
			saving: false,
			saved: false,
			toggling: 0,
			items: [],
			fields: [
				{
					key: 'name',
					label: 'Event',
				},
				{
					key: 'order_token',
					label: 'Order token',
				},
				{
					key: 'is_selling',
					label: 'Remote orders',
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

		async refreshEvents() {

			this.items = (await this.service.index({sort: '!id'})).items;
			this.loaded = true;

		},

		async save() {

			if (!this.model.payment_vouchers) {
				this.model.payment_voucher_value = null;
			}

			this.saving = true;
			if (this.model.id) {
				await this.service.update(this.model.id, this.model);
			} else {
				await this.service.create(this.model);
				this.resetForm();
			}

			this.saving = false;

			this.$refs.editFormModal.hide();
			this.refreshEvents();

		},

		async edit(model, index) {

			this.model = Object.assign({}, model);
			this.$refs.editFormModal.show();

		},

		async remove(model) {

			if (confirm('Are you sure you want to remove this event?')) {
				if (this.model.id === model.id) {
					this.model = {};
				}

				await this.service.delete(model.id);
				await this.refreshEvents();
			}

		},

		async toggleIsSelling(model) {

			this.toggling = model.id;
			model.is_selling = !model.is_selling;
			await this.service.update(model.id, model);
			this.toggling = null;

		},

		createNew() {
			this.resetForm();
			this.$refs.editFormModal.show();
		},

		resetForm() {
			this.model = {
				payment_cards: true,
				allow_unpaid_online_orders: false
			};
		},

		selectOrderToken(evt) {
			evt.preventDefault();
			evt.stopPropagation();
			evt.target.select();
		}

	}
}
</script>
