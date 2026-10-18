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

		<h1>{{ $t('Organisation Settings') }}</h1>

		<p v-if="organisationName">
			<strong>{{ organisationName }}</strong>
			<template v-if="profileId">
				&mdash;
				<a :href="accountsProfileUrl" target="_blank" rel="noopener">{{ $t('Rename on CatLab Accounts') }}</a>
			</template>
		</p>

		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<div v-if="loaded">

			<h2>{{ $t('POS Devices') }}</h2>

			<b-form @submit.prevent="saveTokenLifetime" class="mb-4">
				<b-form-group
					:label="$t('Default device authentication lifetime (days)')"
					label-for="device-token-lifetime"
					:description="$t('Default for newly connected POS devices (1-365 days). You can override it each time you connect a device. Devices that are already paired keep their current expiry until they are paired again.')"
				>
					<b-input-group style="max-width: 16rem">
						<b-form-input
							id="device-token-lifetime"
							type="number"
							min="1"
							max="365"
							step="1"
							required
							v-model="tokenLifetimeDays"
						/>
						<b-input-group-append>
							<b-btn type="submit" variant="success" :disabled="savingTokenLifetime">
								<b-spinner small v-if="savingTokenLifetime" />
								<span class="mr-1" v-if="!savingTokenLifetime">💾</span>
								{{ $t('Save') }}
							</b-btn>
						</b-input-group-append>
					</b-input-group>
					<small v-if="tokenLifetimeSaved" class="text-success d-block mt-1">{{ $t('Saved') }}</small>
					<small v-if="tokenLifetimeError" class="text-danger d-block mt-1">{{ tokenLifetimeError }}</small>
				</b-form-group>
			</b-form>

			<h2>{{ $t('Payment Gateways') }}</h2>
			<p class="text-muted">{{ $t('Configure payment gateways for online top-ups. Credentials are stored encrypted and are never exposed via the API.') }}</p>

			<b-table striped hover :items="gateways" :fields="gatewayFields" v-if="gateways.length > 0">

				<template v-slot:cell(gateway)="row">
					{{ formatGatewayName(row.item.gateway) }}
				</template>

				<template v-slot:cell(has_valid_credentials)="row">
					<b-badge :variant="row.item.has_valid_credentials ? 'success' : 'danger'">
						{{ row.item.has_valid_credentials ? $t('Valid') : $t('Incomplete') }}
					</b-badge>
				</template>

				<template v-slot:cell(is_testing)="row">
					<b-badge :variant="row.item.is_testing ? 'warning' : 'info'">
						{{ row.item.is_testing ? $t('Test') : $t('Live') }}
					</b-badge>
				</template>

				<template v-slot:cell(is_active)="row">
					<b-badge :variant="row.item.is_active ? 'success' : 'secondary'">
						{{ row.item.is_active ? $t('Active') : $t('Inactive') }}
					</b-badge>
				</template>

				<template v-slot:cell(actions)="row">
					<b-dropdown :text="$t('Actions')" size="sm" right>
						<b-dropdown-item @click="editGateway(row.item)">
							✏️ {{ $t('Edit') }}
						</b-dropdown-item>
						<b-dropdown-item @click="removeGateway(row.item)">
							🗑️ {{ $t('Delete') }}
						</b-dropdown-item>
					</b-dropdown>
				</template>

			</b-table>

			<b-alert v-if="gateways.length === 0" show variant="info">
				{{ $t('No payment gateways configured. Add one to enable online top-ups.') }}
			</b-alert>

			<b-button size="sm" variant="success" @click="addGateway">
				<span>＋</span> {{ $t('Add Payment Gateway') }}
			</b-button>

		</div>

		<!-- Add/Edit Gateway Modal -->
		<b-modal :title="editingGateway ? $t('Edit Payment Gateway') : $t('Add Payment Gateway')" ref="gatewayModal" @hide="resetForm">

			<b-form-group :label="$t('Gateway')" label-for="gateway-type" v-if="!editingGateway">
				<b-form-select id="gateway-type" v-model="form.gateway" :options="availableGateways" />
			</b-form-group>

			<div v-if="form.gateway === 'paynl'">
				<b-form-group :label="$t('API Token')" label-for="paynl-api-token" :description="$t('Your Pay.nl token code')">
					<b-form-input id="paynl-api-token" v-model="form.credentials.apiToken" type="text" :placeholder="editingGateway ? $t('(unchanged)') : ''" />
				</b-form-group>

				<b-form-group :label="$t('API Secret')" label-for="paynl-api-secret" :description="$t('Your Pay.nl API token/secret')">
					<b-form-input id="paynl-api-secret" v-model="form.credentials.apiSecret" type="password" :placeholder="editingGateway ? $t('(unchanged)') : ''" />
				</b-form-group>

				<b-form-group :label="$t('Service ID')" label-for="paynl-service-id" :description="$t('Your Pay.nl service ID (e.g. SL-xxxx-xxxx)')">
					<b-form-input id="paynl-service-id" v-model="form.credentials.serviceId" type="text" :placeholder="editingGateway ? $t('(unchanged)') : ''" />
				</b-form-group>
			</div>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="form.is_testing" />
					{{ $t('Test mode') }}
				</label>
			</b-form-group>

			<b-form-group>
				<label>
					<input type="checkbox" v-model="form.is_active" />
					{{ $t('Active') }}
				</label>
			</b-form-group>

			<template #modal-footer>
				<b-btn variant="light" @click="resetForm">{{ $t('Cancel') }}</b-btn>
				<b-btn variant="success" @click="saveGateway" :disabled="saving">
					<b-spinner small v-if="saving" />
					<span class="mr-1" v-if="!saving">💾</span>
					{{ $t('Save') }}
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
			this.loadOrganisation();
			this.loadGateways();
		},

		data() {
			const currentOrganisation = (window.ORGANISATIONS || []).find(
				(organisation) => organisation.id === window.ORGANISATION_ID
			) || null;

			return {
				organisationName: currentOrganisation ? currentOrganisation.name : '',
				profileId: currentOrganisation ? currentOrganisation.profile_id : null,
				loaded: false,
				saving: false,
				organisation: null,
				tokenLifetimeDays: 7,
				savingTokenLifetime: false,
				tokenLifetimeSaved: false,
				tokenLifetimeError: null,
				gateways: [],
				editingGateway: null,
				gatewayFields: [
					{ key: 'gateway', label: this.$t('Gateway') },
					{ key: 'has_valid_credentials', label: this.$t('Credentials') },
					{ key: 'is_testing', label: this.$t('Mode') },
					{ key: 'is_active', label: this.$t('Status') },
					{ key: 'actions', label: this.$t('Actions'), class: 'text-right' }
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

		computed: {
			accountsProfileUrl() {
				return (window.CATLAB_DRINKS_CONFIG.ACCOUNTS_URL || 'https://accounts.catlab.eu')
					+ '/profiles/' + this.profileId;
			},
		},

		methods: {

			async loadOrganisation() {
				this.organisation = await this.$organisationService.get(window.ORGANISATION_ID);
				if (this.organisation && this.organisation.device_token_lifetime_days) {
					this.tokenLifetimeDays = this.organisation.device_token_lifetime_days;
				}
			},

			async saveTokenLifetime() {
				const days = parseInt(this.tokenLifetimeDays, 10);
				this.tokenLifetimeSaved = false;
				this.tokenLifetimeError = null;

				if (isNaN(days) || days < 1 || days > 365) {
					this.tokenLifetimeError = this.$t('Please enter a number of days between 1 and 365.');
					return;
				}

				this.savingTokenLifetime = true;
				try {
					// The API requires the name on every update; sending it unchanged is a no-op.
					this.organisation = await this.$organisationService.update(window.ORGANISATION_ID, {
						name: this.organisation ? this.organisation.name : this.organisationName,
						device_token_lifetime_days: days
					});
					this.tokenLifetimeDays = this.organisation.device_token_lifetime_days || days;
					this.tokenLifetimeSaved = true;
				} catch (e) {
					console.error(e);
					this.tokenLifetimeError = this.$t('Could not save the setting.');
				} finally {
					this.savingTokenLifetime = false;
				}
			},

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
				if (confirm(this.$t('Are you sure you want to remove the {gateway} payment gateway?', { gateway: this.formatGatewayName(item.gateway) }))) {
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
