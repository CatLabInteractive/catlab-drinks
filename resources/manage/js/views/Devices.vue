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
			Point of sale devices

			<b-button size="sm" class="btn-success" @click="createNew" title="Pair a device">
				<i class="fas fa-link"></i>
				<span class="sr-only">Link or authenticate a device</span>
			</b-button>
		</h1>
		<div class="text-center" v-if="!loaded">
			<b-spinner label="Loading data" />
		</div>

		<b-row>
			<b-col>
				<b-table striped hover :items="items" :fields="fields" v-if="loaded">

					<template v-slot:cell(name)="row">
						{{ row.item.name }}
					</template>

					<template v-slot:cell(license)="row">
						<span v-if="row.item.license_key" class="text-success">
							<i class="fas fa-check-circle"></i> Licensed
						</span>
						<span v-else class="text-muted">
							<i class="fas fa-times-circle"></i> No license
						</span>
					</template>

					<template v-slot:cell(actions)="row">

						<b-dropdown text="Actions" size="sm" right>

							<b-dropdown-item class="" @click="edit(row.item)" title="Edit">
								<i class="fas fa-edit"></i>
								Edit
							</b-dropdown-item>

							<b-dropdown-item :href="buyLicenseUrl(row.item)" title="Buy License">
								<i class="fas fa-key"></i>
								Buy License
							</b-dropdown-item>

							<b-dropdown-item @click="enterLicense(row.item)" title="Enter License">
								<i class="fas fa-paste"></i>
								Enter License
							</b-dropdown-item>

							<b-dropdown-item @click="remove(row.item)" title="Remove">
								<i class="fas fa-trash"></i>
								Delete
							</b-dropdown-item>

						</b-dropdown>

					</template>

				</b-table>
			</b-col>
		</b-row>

	</b-container>

	<!-- Connect / pair device modal -->
	<b-modal :title="'Connect & authenticate a device'" @hide="resetConnectForm" ref="connectFormModal">

		<div class="text-center">
			<b-spinner v-if="creatingRequest" label="Creating connect token" />
		</div>

		<div v-if="!creatingRequest && connectRequest">

			<div v-if="connectRequest.state === 'pending'" class="text-center">
				<p>Scan this QR code with the POS device to connect:</p>
				<qrcode-vue :value="connectUrl" :size="256" level="M" />
				<div class="mt-2">
					<b-form-group label="Connection URL" label-for="connect-url" description="You can also copy this URL and paste it in the POS device's manual token entry">
						<b-form-input id="connect-url" type="text" :value="connectUrl" readonly @click="selectConnectUrl" />
					</b-form-group>
				</div>

				<p>- or -</p>
				<p><a target="_blank" :href="connectUrl" class="btn btn-primary">Open POS on this device</a></p>
			</div>

			<div v-if="connectRequest.state === 'requires_pairing_code'">

				<b-alert variant="info" show>
					<i class="fas fa-info-circle mr-1"></i>
					This is a new device and needs to be paired. Enter the pairing code displayed on the POS device below.
				</b-alert>

				<b-form-group label="Pairing Code" label-for="pairing-code" description="The code shown on the POS device screen">
					<b-form-input id="pairing-code" type="text" v-model="pairingCode" placeholder="Enter pairing code" />
				</b-form-group>

				<b-form-group label="Device Name" label-for="device-name" description="A descriptive name to identify this device (e.g. 'Bar Terminal 1')">
					<b-form-input id="device-name" type="text" v-model="deviceName" placeholder="Enter device name" />
				</b-form-group>

				<b-button variant="primary" @click="submitPairingCode" block>
					<i class="fas fa-check mr-1"></i> Pair Device
				</b-button>
			</div>

		</div>

		<template #modal-footer>
			<b-btn type="button" variant="light" @click="resetConnectForm()">Cancel</b-btn>
		</template>
	</b-modal>

	<!-- Edit device modal -->
	<b-modal :title="'Edit device'" @hide="resetEditForm" ref="editFormModal">

		<b-form-group label="Device Name" label-for="edit-device-name" description="A descriptive name to identify this device">
			<b-form-input id="edit-device-name" type="text" v-model="editModel.name" placeholder="Enter device name" />
		</b-form-group>

		<template #modal-footer>
			<b-btn type="button" variant="light" @click="resetEditForm()">Cancel</b-btn>
			<b-btn type="button" variant="success" @click="saveEdit()">
				<i class="fas fa-save mr-1"></i> Save
			</b-btn>
		</template>
	</b-modal>

	<!-- Enter license modal -->
	<b-modal :title="'Enter license for ' + (licenseDevice ? licenseDevice.name : '')" @hide="resetLicenseForm" ref="licenseFormModal">

		<b-form-group label="License Key" label-for="license-key-input" description="Paste the base64-encoded license text block here">
			<b-form-textarea id="license-key-input" v-model="licenseKey" placeholder="Paste license key here" rows="6" />
		</b-form-group>

		<b-alert variant="danger" :show="!!licenseError">
			<i class="fas fa-exclamation-triangle mr-1"></i> {{ licenseError }}
		</b-alert>

		<template #modal-footer>
			<b-btn type="button" variant="light" @click="resetLicenseForm()">Cancel</b-btn>
			<b-btn type="button" variant="success" @click="submitLicense()" :disabled="!licenseKey">
				<i class="fas fa-check mr-1"></i> Apply License
			</b-btn>
		</template>
	</b-modal>

</template>

<script>

	import { DeviceService } from '../services/DeviceService';
	import QrcodeVue from 'qrcode.vue';

	export default {

		components: {
			QrcodeVue
		},

		mounted() {

			this.service = new DeviceService(window.ORGANISATION_ID);
			this.refreshDevices().then(() => {
				this.handleLicenseReturn();
			});

		},

		data() {
			return {
				loaded: false,
				items: [],
				fields: [
					{
						key: 'name',
						label: 'Device',
					},
					{
						key: 'license',
						label: 'License',
					},
					{
						key: 'actions',
						label: 'Actions',
						class: 'text-right'
					}
				],
				creatingRequest: false,
				connectRequest: null,
				pinger: null,
				pairingCode: null,
				deviceName: null,
				editModel: {},
				licenseDevice: null,
				licenseKey: null,
				licenseError: null
			}
		},

		computed: {
			connectUrl() {
				if (this.connectRequest && this.connectRequest.url) {
					return 'https://drinks.catlab.eu/connect?data=' + encodeURIComponent(this.connectRequest.url);
				}
				return '';
			}
		},

		methods: {

			async refreshDevices() {

				this.items = (await this.service.index({ sort: '!id' })).items;
				this.loaded = true;

			},

			async createNew() {

				if (this.pinger) {
					clearInterval(this.pinger);
				}

				this.$refs.connectFormModal.show();

				this.creatingRequest = true;
				this.connectRequest = await this.service.createConnectRequest();
				this.creatingRequest = false;

				// Start pinger.
				this.pinger = setInterval(async () => {
					this.connectRequest = await this.service.pingConnectRequest(this.connectRequest);

					// Process any updates that we might have.
					switch (this.connectRequest.state) {
						case 'accepted':
							this.resetConnectForm();
							this.refreshDevices();
							break;
					}

				}, 1000);
			},

			resetConnectForm() {
				this.$refs.connectFormModal.hide();
				this.pairingCode = null;
				this.deviceName = null;
				this.connectRequest = null;

				if (this.pinger) {
					clearInterval(this.pinger);
				}
			},

			async submitPairingCode() {

				await this.service.submitPairingCode(this.connectRequest, this.pairingCode, this.deviceName);

			},

			edit(item) {
				this.editModel = Object.assign({}, item);
				this.$refs.editFormModal.show();
			},

			resetEditForm() {
				this.$refs.editFormModal.hide();
				this.editModel = {};
			},

			async saveEdit() {
				await this.service.update(this.editModel.id, { name: this.editModel.name });
				this.resetEditForm();
				this.refreshDevices();
			},

			async remove(item) {

				if (confirm('Are you sure you want to delete device "' + item.name + '"?\n\nThis will revoke the device\'s access token and it will no longer be able to connect. The device will need to be re-paired to use it again.')) {
					await this.service.delete(item.id);
					await this.refreshDevices();
				}

			},

			selectConnectUrl(evt) {
				evt.target.select();
			},

			buyLicenseUrl(device) {
				const returnUrl = window.location.origin + window.CATLAB_DRINKS_CONFIG.ROUTER_BASE + 'devices?device_id=' + encodeURIComponent(device.id);
				return 'https://accounts.catlab.eu/licenses/10/buy?data[device_uid]=' + encodeURIComponent(device.uid) + '&return=' + encodeURIComponent(returnUrl);
			},

			async handleLicenseReturn() {
				const urlParams = new URLSearchParams(window.location.search);
				const license = urlParams.get('license');
				const deviceId = urlParams.get('device_id');

				if (license && deviceId) {
					try {
						await this.service.setLicense(deviceId, license);
						await this.refreshDevices();
					} catch (e) {
						console.error('Failed to store license:', e);
					}

					// Clean URL parameters
					window.history.replaceState({}, document.title, window.location.pathname);
				}
			},

			enterLicense(item) {
				this.licenseDevice = item;
				this.licenseKey = null;
				this.licenseError = null;
				this.$refs.licenseFormModal.show();
			},

			resetLicenseForm() {
				this.$refs.licenseFormModal.hide();
				this.licenseDevice = null;
				this.licenseKey = null;
				this.licenseError = null;
			},

			validateLicenseLocally(licenseText, device) {
				let decoded;
				try {
					decoded = atob(licenseText.trim());
				} catch (e) {
					return 'Invalid license key: not valid base64.';
				}

				let license;
				try {
					license = JSON.parse(decoded);
				} catch (e) {
					return 'Invalid license key: invalid JSON structure.';
				}

				if (!license.data) {
					return 'Invalid license key: missing license data.';
				}

				if (!license.signature) {
					return 'Invalid license key: missing signature.';
				}

				if (!license.data.device_uid) {
					return 'Invalid license key: missing device_uid in license data.';
				}

				if (license.data.device_uid !== device.uid) {
					return 'Invalid license key: this license is for a different device.';
				}

				if (license.data.expiration_date !== null && license.data.expiration_date !== undefined) {
					const expirationDate = new Date(license.data.expiration_date);
					if (isNaN(expirationDate.getTime())) {
						return 'Invalid license key: invalid expiration date format.';
					}
					if (expirationDate < new Date()) {
						return 'Invalid license key: license has expired.';
					}
				}

				return null;
			},

			async submitLicense() {
				this.licenseError = null;

				const validationError = this.validateLicenseLocally(this.licenseKey, this.licenseDevice);
				if (validationError) {
					this.licenseError = validationError;
					return;
				}

				try {
					await this.service.setLicense(this.licenseDevice.id, this.licenseKey.trim());
					this.resetLicenseForm();
					await this.refreshDevices();
				} catch (e) {
					this.licenseError = e.response && e.response.data && e.response.data.error
						? e.response.data.error.message
						: 'Failed to save license. Please try again.';
				}
			},

		}
	}
</script>
