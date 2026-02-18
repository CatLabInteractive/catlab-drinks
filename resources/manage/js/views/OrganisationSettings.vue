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

		<h1>Organisation Settings</h1>

		<div class="text-center" v-if="!loaded">
			<b-spinner label="Loading data" />
		</div>

		<div v-if="loaded">

			<h2>Payment Gateways</h2>
			<p class="text-muted">Configure payment gateways for online top-ups. Credentials are stored encrypted and are never exposed via the API.</p>

			<b-table striped hover :items="gateways" :fields="gatewayFields" v-if="gateways.length > 0">

				<template v-slot:cell(gateway)="row">
					{{ formatGatewayName(row.item.gateway) }}
				</template>

				<template v-slot:cell(has_valid_credentials)="row">
					<b-badge :variant="row.item.has_valid_credentials ? 'success' : 'danger'">
						{{ row.item.has_valid_credentials ? 'Valid' : 'Incomplete' }}
					</b-badge>
				</template>

				<template v-slot:cell(is_testing)="row">
					<b-badge :variant="row.item.is_testing ? 'warning' : 'info'">
						{{ row.item.is_testing ? 'Test' : 'Live' }}
					</b-badge>
				</template>

				<template v-slot:cell(is_active)="row">
					<b-badge :variant="row.item.is_active ? 'success' : 'secondary'">
						{{ row.item.is_active ? 'Active' : 'Inactive' }}
					</b-badge>
				</template>

				<template v-slot:cell(actions)="row">
					<b-dropdown text="Actions" size="sm" right>
						<b-dropdown-item @click="editGateway(row.item)">
							✏️ Edit
						</b-dropdown-item>
						<b-dropdown-item @click="removeGateway(row.item)">
							🗑️ Delete
						</b-dropdown-item>
					</b-dropdown>
				</template>

			</b-table>

			<b-alert v-if="gateways.length === 0" show variant="info">
				No payment gateways configured. Add one to enable online top-ups.
			</b-alert>

			<b-button size="sm" variant="success" @click="addGateway">
				<span>＋</span> Add Payment Gateway
			</b-button>

		</div>

		<!-- Add/Edit Gateway Modal -->
		<b-modal :title="editingGateway ? 'Edit Payment Gateway' : 'Add Payment Gateway'" ref="gatewayModal" @hide="resetForm">

			<b-form-group label="Gateway" label-for="gateway-type" v-if="!editingGateway">
				<b-form-select id="gateway-type" v-model="form.gateway" :options="availableGateways" />
			</b-form-group>

			<div v-if="form.gateway === 'paynl'">
				<b-form-group label="API Token" label-for="paynl-api-token" description="Your Pay.nl token code">
					<b-form-input id="paynl-api-token" v-model="form.credentials.apiToken" type="text" :placeholder="editingGateway ? '(unchanged)' : ''" />
				</b-form-group>

				<b-form-group label="API Secret" label-for="paynl-api-secret" description="Your Pay.nl API token/secret">
					<b-form-input id="paynl-api-secret" v-model="form.credentials.apiSecret" type="password" :placeholder="editingGateway ? '(unchanged)' : ''" />
				</b-form-group>

				<b-form-group label="Service ID" label-for="paynl-service-id" description="Your Pay.nl service ID (e.g. SL-xxxx-xxxx)">
					<b-form-input id="paynl-service-id" v-model="form.credentials.serviceId" type="text" :placeholder="editingGateway ? '(unchanged)' : ''" />
				</b-form-group>
			</div>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="form.is_testing" />
					Test mode
				</label>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="form.is_active" />
					Active
				</label>
			</b-form-group>

			<template #modal-footer>
				<b-btn variant="light" @click="resetForm">Cancel</b-btn>
				<b-btn variant="success" @click="saveGateway" :disabled="saving">
					<b-spinner small v-if="saving" />
					<span class="mr-1" v-if="!saving">💾</span>
					Save
				</b-btn>
			</template>

		</b-modal>

	</b-container>
</template>

<script>

	import { PaymentGatewayService } from '../services/PaymentGatewayService';

	export default {

		mounted() {
			this.service = new PaymentGatewayService(window.ORGANISATION_ID);
			this.loadGateways();
		},

		data() {
			return {
				loaded: false,
				saving: false,
				gateways: [],
				editingGateway: null,
				gatewayFields: [
					{ key: 'gateway', label: 'Gateway' },
					{ key: 'has_valid_credentials', label: 'Credentials' },
					{ key: 'is_testing', label: 'Mode' },
					{ key: 'is_active', label: 'Status' },
					{ key: 'actions', label: 'Actions', class: 'text-right' }
				],
				availableGateways: [
					{ value: 'paynl', text: 'Pay.nl' }
				],
				form: {
					gateway: 'paynl',
					credentials: {},
					is_testing: false,
					is_active: true
				}
			}
		},

		methods: {

			async loadGateways() {
				const response = await this.service.index();
				this.gateways = response.items;
				this.loaded = true;
			},

			formatGatewayName(gateway) {
				const names = { 'paynl': 'Pay.nl' };
				return names[gateway] || gateway;
			},

			addGateway() {
				this.editingGateway = null;
				this.form = {
					gateway: 'paynl',
					credentials: {},
					is_testing: false,
					is_active: true
				};
				this.$refs.gatewayModal.show();
			},

			editGateway(item) {
				this.editingGateway = item;
				this.form = {
					gateway: item.gateway,
					credentials: {},
					is_testing: item.is_testing,
					is_active: item.is_active
				};
				this.$refs.gatewayModal.show();
			},

			async saveGateway() {
				this.saving = true;

				try {
					const data = {
						is_testing: this.form.is_testing,
						is_active: this.form.is_active
					};

					// Only include credentials if any were filled in
					const hasCredentials = Object.values(this.form.credentials).some(v => v && v.length > 0);
					if (hasCredentials) {
						data.credentials = this.form.credentials;
					}

					if (this.editingGateway) {
						await this.service.update(this.editingGateway.id, data);
					} else {
						data.gateway = this.form.gateway;
						if (!data.credentials) {
							data.credentials = this.form.credentials;
						}
						await this.service.create(data);
					}

					this.resetForm();
					await this.loadGateways();
				} catch (e) {
					console.error(e);
				} finally {
					this.saving = false;
				}
			},

			async removeGateway(item) {
				if (confirm('Are you sure you want to remove the ' + this.formatGatewayName(item.gateway) + ' payment gateway?')) {
					await this.service.delete(item.id);
					await this.loadGateways();
				}
			},

			resetForm() {
				this.$refs.gatewayModal.hide();
				this.editingGateway = null;
				this.form = {
					gateway: 'paynl',
					credentials: {},
					is_testing: false,
					is_active: true
				};
			}
		}
	}
</script>
