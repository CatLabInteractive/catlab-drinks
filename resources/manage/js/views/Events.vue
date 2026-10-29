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
			{{ $t('Events') }}

			<b-button size="sm" class="btn-success" @click="createNew" :title="$t('Create new event')">
				<span>＋</span>
				<span class="sr-only">{{ $t('Create new event') }}</span>
			</b-button>
		</h1>
		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')"/>
		</div>

		<b-row>
			<b-col>
				<b-table striped hover :items="items" :fields="fields" v-if="loaded">

					<template v-slot:cell(name)="row">
						<router-link :to="{ name: 'menu', params: { id: row.item.id } }">{{ row.item.name }}</router-link>
					</template>

					<template v-slot:cell(actions)="row">

						<b-dropdown :text="$t('Actions')" size="sm" right>

							<b-dropdown-item class="" @click="edit(row.item, row.index)" :title="$t('Edit')">
								✏️
								{{ $t('Edit') }}
							</b-dropdown-item>

							<b-dropdown-item @click="remove(row.item)" :title="$t('Delete')">
								🗑️
								{{ $t('Delete') }}
							</b-dropdown-item>

							<b-dropdown-divider></b-dropdown-divider>

							<b-dropdown-group :header="$t('Sales')">

								<b-dropdown-item :to="{ name: 'menu', params: { id: row.item.id } }" :title="$t('Edit menu')">
									📜
									{{ $t('Edit menu') }}
								</b-dropdown-item>

								<b-dropdown-item :to="{ name: 'summary', params: { id: row.item.id } }"
												 :title="$t('Sales overview')">
									📊
									{{ $t('Sales overview') }}
								</b-dropdown-item>

								<b-dropdown-item :href="row.item.order_url" target="_blank" :title="$t('Client order form')">
									👤
									{{ $t('Client order form') }}
								</b-dropdown-item>

								<b-dropdown-item @click="showOrderToken(row.item)" :title="$t('Remote order token')">
									🔑
									{{ $t('Remote order token') }}
								</b-dropdown-item>


								<b-dropdown-item :to="{ name: 'sales', params: { id: row.item.id } }">
									📋
									{{ $t('Order history') }}
								</b-dropdown-item>

							</b-dropdown-group>

							<b-dropdown-divider></b-dropdown-divider>

							<b-dropdown-group :header="$t('Attendees')">
								<b-dropdown-item :to="{ name: 'attendees', params: { id: row.item.id } }"
												 :title="$t('Register attendees')">
									👥
									{{ $t('Register attendees') }}
								</b-dropdown-item>
							</b-dropdown-group>

						</b-dropdown>


					</template>

					<template v-slot:cell(is_selling)="row">

						<b-button v-if="!row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)"
								  class="btn-danger">
							{{ $t('Closed') }}
						</b-button>

						<b-button v-if="row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)"
								  class="btn-success">
							{{ $t('Open') }}
						</b-button>

						<b-spinner v-if="toggling === row.item.id" small></b-spinner>
					</template>

				</b-table>
			</b-col>
		</b-row>

		<b-row class="mt-4">
			<b-col>
				<b-card>
					<h5><span class="mr-1">📱</span> {{ $t('POS Device Pairing') }}</h5>
					<p class="text-muted">
						{{ $t('POS (Point of Sale) devices authenticate separately from your management account.') }}
						{{ $t('Each POS terminal requires its own pairing to ensure secure, independent operation—even if the management session expires, POS devices continue to function.') }}
					</p>
					<b-button variant="primary" :to="{ name: 'devices' }">
						<span class="mr-1">🔗</span> {{ $t('Manage & Pair Devices') }}
					</b-button>
				</b-card>
			</b-col>
		</b-row>

	</b-container>

	<b-modal :title="modalTitle" hide-footer @hide="resetForm" ref="editFormModal">
		<event-form :event="model" :mode="model.id ? 'single' : 'wizard'" :saving="saving"
					@submit="save" @cancel="$refs.editFormModal.hide()" @step="wizardStep = $event" />
	</b-modal>

	<b-modal :title="$t('Remote order token')" ok-only ref="orderTokenModal">
		<div v-if="orderTokenEvent">
			<h5>{{ orderTokenEvent.name }}</h5>

			<b-form-group :label="$t('Order page URL')"
						  :description="$t('Share this link with your customers to let them order remotely.')">
				<input @click="selectOrderToken($event)" :value="orderTokenEvent.order_url"
					   class="form-control order-token" readonly />
			</b-form-group>

			<b-form-group :label="$t('Order token')">
				<input @click="selectOrderToken($event)" :value="orderTokenEvent.full_order_token"
					   class="form-control order-token" readonly />
			</b-form-group>

			<p class="text-muted small">
				{{ $t('The order token consists of a public part and a secret part, separated by a dash. The public part identifies the event in the order page URL. The secret part is used by integrating applications, such as QuizWitz, to sign order parameters (card and name) with HMAC-SHA256.') }}
				{{ $t('Only share the full token with trusted integrations.') }}
			</p>
		</div>
	</b-modal>

</template>

<script>

import {EventService} from "../../../shared/js/services/EventService";
import EventForm from '../components/EventForm.vue';

export default {
	components: {
		EventForm,
	},

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
					label: this.$t('Event'),
				},
				{
					key: 'is_selling',
					label: this.$t('Remote orders'),
					class: 'text-center'
				},
				{
					key: 'actions',
					label: this.$t('Actions'),
					class: 'text-right'
				}
			],
			model: {},
			wizardStep: 1,
			orderTokenEvent: null
		}
	},

	computed: {
		modalTitle() {
			if (this.model.id) {
				return this.$t('Edit event ID#{id}', { id: this.model.id });
			}
			return this.$t('New event: step {step} of 3', { step: this.wizardStep });
		}
	},

	methods: {

		async refreshEvents() {

			this.items = (await this.service.index({sort: '!id'})).items;
			this.loaded = true;

		},

		async save(payload) {

			this.saving = true;
			try {
				if (payload.id) {
					await this.service.update(payload.id, payload);
				} else {
					await this.service.create(payload);
				}
			} finally {
				this.saving = false;
			}

			this.$refs.editFormModal.hide();
			this.refreshEvents();

		},

		async edit(model, index) {

			this.model = Object.assign({}, model);
			this.$refs.editFormModal.show();

		},

		async remove(model) {

			if (confirm(this.$t('Are you sure you want to remove this event?'))) {
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
			this.model = {};
			this.wizardStep = 1;
		},

		showOrderToken(event) {
			this.orderTokenEvent = event;
			this.$refs.orderTokenModal.show();
		},

		selectOrderToken(evt) {
			evt.preventDefault();
			evt.stopPropagation();
			evt.target.select();
		}

	}
}
</script>
