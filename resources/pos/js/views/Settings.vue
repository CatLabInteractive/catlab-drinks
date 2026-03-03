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

		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<b-row v-if="loaded">

			<b-col cols="12">

				<h2>{{ $t('Point of sale settings') }}</h2>

				<b-form @submit="onSubmit" @reset="onReset">

					<b-form-fieldset>
						<legend>{{ $t('General settings') }}</legend>

						<b-form-group
							id="device-name-group"
							:label="$t('Device name')"
							label-for="device-name"
							:description="$t('Name of this device as configured on the server.')"
						>
							<b-form-input
								id="device-name"
								v-model="deviceName"
								type="text"
								disabled
								:placeholder="$t('Device name')"
							></b-form-input>
						</b-form-group>

						<b-form-group
							id="allow_live_orders"
							:description="$t('This terminal can process orders at the bar')"
						>
							<label>
								<input type="checkbox" v-model="allowLiveOrders"></input>
								{{ $t('Allow live orders at this terminal') }}<br />
							</label>
						</b-form-group>

						<b-form-group
							id="allow_remote_orders"
							:description="$t('This terminal can process orders from tables')"
						>
							<label>
								<input type="checkbox" v-model="allowRemoteOrders"></input>
								{{ $t('Allow remote orders at this terminal') }}<br />
							</label>
						</b-form-group>

					</b-form-fieldset>

					<hr />

					<b-form-fieldset>
						<legend>{{ $t('Remote NFC reader') }}</legend>
						<p class="text-muted">
							{{ $t('Requires') }} <a href="https://github.com/CatLabInteractive/nfc-socketio" target="_blank">{{ $t('an additional service') }}</a>.
						</p>

						<b-form-group
							id="nfc-server-group"
							:label="$t('NFC webserver url')"
							label-for="nfc-server"
						>
							<b-form-input
								id="nfc-server"
								v-model="nfcServer"
								type="text"
								:placeholder="$t('NFC Server url')"
							></b-form-input>
						</b-form-group>

						<b-form-group
							id="nfc-password-group"
							:label="$t('NFC webserver password')"
							label-for="nfc-server"
						>
							<b-form-input
								id="nfc-password"
								v-model="nfcPassword"
								type="text"
								:placeholder="$t('NFC Server password')"
							></b-form-input>
						</b-form-group>

					</b-form-fieldset>

					<b-button type="submit" variant="primary">{{ $t('Save') }}</b-button>
					<b-button type="reset" variant="danger">{{ $t('Reset') }}</b-button>
				</b-form>

				<hr v-if="licenseStatus" />

				<b-form-fieldset v-if="licenseStatus">
					<legend>{{ $t('License') }}</legend>
					<div v-if="licenseStatus.valid">
						<b-alert variant="success" :show="true">
							<span class="mr-1">✅</span> {{ $t('License is active.') }}
						</b-alert>
						<p v-if="licenseStatus.expirationDate">
							<strong>{{ $t('Expires:') }}</strong> {{ formatDate(licenseStatus.expirationDate) }}
						</p>
					</div>
					<div v-else>
						<b-alert variant="warning" :show="true">
							<span class="mr-1">⚠️</span> {{ $t('No active license.') }}
						</b-alert>
						<p>
							<strong>{{ $t('Cards scanned:') }}</strong> {{ licenseStatus.scannedCards }} / {{ licenseStatus.maxCards }}<br />
							<strong>{{ $t('Remaining:') }}</strong> {{ licenseStatus.remainingCards }}
						</p>
						<p class="text-muted">
							{{ $t('Please purchase a license to remove the card scan limit.') }}
							{{ $t('Visit the management portal to buy and activate a license for this device.') }}
						</p>
					</div>
				</b-form-fieldset>

				<hr />

				<b-form-fieldset>
					<legend>{{ $t('Sync status') }}</legend>

					<div v-if="isOffline" class="mb-3">
						<b-badge variant="warning">{{ $t('Offline') }}</b-badge>
					</div>

					<p>
						<strong>{{ $t('Last synced:') }}</strong>
						{{ lastSyncTime ? formatDateTime(lastSyncTime) : $t('Never') }}
					</p>

					<p>
						<strong>{{ $t('Pending transactions:') }}</strong>&nbsp;
						<span v-if="pendingTransactionCount > 0" class="text-warning">{{ pendingTransactionCount }}</span>
						<span v-else class="text-success">0</span>
					</p>

					<p>
						<strong>{{ $t('Pending queue items:') }}</strong>&nbsp;
						<span v-if="pendingQueueCount > 0" class="text-warning">{{ pendingQueueCount }}</span>
						<span v-else class="text-success">0</span>
					</p>

					<p v-if="pendingTransactionCount > 0 || pendingQueueCount > 0" class="text-muted">
						{{ $t('These transactions will be uploaded automatically when the connection is restored.') }}
					</p>

					<b-button variant="outline-primary" @click="syncNow" :disabled="isSyncing">
						<b-spinner small v-if="isSyncing" class="mr-1"></b-spinner>
						<span v-else class="mr-1">🔄</span>
						{{ $t('Sync now') }}
					</b-button>

					<b-alert v-if="syncError" variant="danger" :show="true" class="mt-2">
						{{ syncError }}
					</b-alert>

					<b-alert v-if="syncSuccess" variant="success" :show="true" class="mt-2">
						{{ $t('Synchronization complete.') }}
					</b-alert>
				</b-form-fieldset>

				<hr />

				<b-form-fieldset>
					<legend>{{ $t('Device') }}</legend>
					<p class="text-muted">{{ $t('Disconnect this device from the server. You will need to re-pair it to use it again.') }}</p>
					<b-button variant="outline-danger" @click="logout" :disabled="isLoggingOut">
						<b-spinner small v-if="isLoggingOut" class="mr-1"></b-spinner>
						<span v-else class="mr-1">🚪</span> {{ $t('Logout') }}
					</b-button>
				</b-form-fieldset>

			</b-col>
		</b-row>

	</b-container>

</template>

<script>

	import { clearAuthData } from '../../../shared/js/services/DeviceAuth';
	import { PosDeviceService } from '../../../shared/js/services/PosDeviceService';
	import { AbstractOfflineQueue } from '../../../shared/js/services/AbstractOfflineQueue';

	export default {

		props: [

		],

		async mounted() {

			this.settingService = this.$settingService;

			await this.settingService.load();
			this.onReset();

			// Load device name from the global set during app initialization
			if (window.DEVICE_NAME) {
				this.deviceName = window.DEVICE_NAME;
			}

			// Load license status if LicenseService is available
			if (typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.LicenseService) {
				try {
					const licenseService = new window.CATLAB_DRINKS_APP.LicenseService();
					this.licenseStatus = await licenseService.getLicenseStatus();
				} catch (e) {
					console.error('Failed to load license status:', e);
				}
			}

			// Track offline status and sync info
			this.isOffline = !this.$offlineManager.isOnline();
			this.lastSyncTime = this.$offlineManager.getLastSyncTime();
			this._offlineListener = this.$offlineManager.on((online) => {
				this.isOffline = !online;
				if (online) {
					this.lastSyncTime = this.$offlineManager.getLastSyncTime();
				}
				this.refreshPendingTransactionCount();
			});

			// Load pending transaction count
			await this.refreshPendingTransactionCount();

			this.loaded = true;
		},

		beforeDestroy() {
			if (this._offlineListener) {
				this._offlineListener.unbind();
			}
		},

		data() {
			return {
				loaded: false,
				deviceName: '',
				nfcServer: '',
				nfcPassword: '',

				allowLiveOrders: false,
				allowRemoteOrders: false,

				licenseStatus: null,

				isOffline: false,
				lastSyncTime: null,
				pendingTransactionCount: 0,
				pendingQueueCount: 0,

				isSyncing: false,
				syncError: null,
				syncSuccess: false,
				isLoggingOut: false
			}
		},

		watch: {

		},

		methods: {

			formatDate(value) {
				if (value) {
					return new Date(value).toLocaleDateString();
				}
				return '';
			},

			formatDateTime(value) {
				if (value) {
					const d = new Date(value);
					return d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
				}
				return '';
			},

			async refreshPendingTransactionCount() {
				if (this.$cardService) {
					try {
						this.pendingTransactionCount = await this.$cardService.getPendingTransactionCount();
					} catch (e) {
						console.warn('Failed to get pending transaction count:', e);
					}
				}

				try {
					this.pendingQueueCount = await AbstractOfflineQueue.getAllPendingCount();
				} catch (e) {
					console.warn('Failed to get pending queue count:', e);
				}
			},

			async syncNow() {
				this.isSyncing = true;
				this.syncError = null;
				this.syncSuccess = false;

				try {
					const promises = [];

					promises.push(AbstractOfflineQueue.uploadAllPending());

					if (this.$cardService) {
						promises.push(this.$cardService.syncPendingTransactions());
					}

					await Promise.all(promises);
					this.syncSuccess = true;
				} catch (e) {
					console.error('Sync failed:', e);
					this.syncError = e.message || String(e);
				} finally {
					this.isSyncing = false;
					await this.refreshPendingTransactionCount();
				}
			},

			async logout() {
				this.isLoggingOut = true;
				this.syncError = null;

				try {
					// Attempt to sync all pending data first
					const promises = [];
					promises.push(AbstractOfflineQueue.uploadAllPending());
					if (this.$cardService) {
						promises.push(this.$cardService.syncPendingTransactions());
					}
					await Promise.all(promises);
				} catch (e) {
					console.warn('Sync before logout failed:', e);
				}

				// Refresh counts after sync attempt
				await this.refreshPendingTransactionCount();

				const hasPending = this.pendingTransactionCount > 0 || this.pendingQueueCount > 0;

				if (hasPending) {
					if (!confirm(this.$t('There are still {count} pending items that have not been uploaded. Logging out may cause data loss. Are you sure you want to logout?', { count: this.pendingTransactionCount + this.pendingQueueCount }))) {
						this.isLoggingOut = false;
						return;
					}
				} else {
					if (!confirm(this.$t('Are you sure you want to logout? This device will need to be re-paired to connect again.'))) {
						this.isLoggingOut = false;
						return;
					}
				}

				clearAuthData().then(() => {
					window.location.reload();
				});
			},

			onSubmit(evt) {
				evt.preventDefault();

				this.settingService.nfcServer = this.nfcServer;
				this.settingService.nfcPassword = this.nfcPassword;

				this.settingService.allowLiveOrders = this.allowLiveOrders;
				this.settingService.allowRemoteOrders = this.allowRemoteOrders;

				// Sync order settings to the server
				const posDeviceService = new PosDeviceService();
				Promise.all([
					this.settingService.save(),
					posDeviceService.updateCurrentDevice({
						allow_remote_orders: this.allowRemoteOrders,
						allow_live_orders: this.allowLiveOrders
					})
				]).then(function() {
					window.location.reload();
				}).catch(function(e) {
					console.error('Failed to save settings:', e);
					alert('Failed to save settings. Please try again.');
				});

			},

			onReset(evt = null) {
				if (evt) {
					evt.preventDefault();
				}

				this.nfcServer = this.settingService.nfcServer;
				this.nfcPassword = this.settingService.nfcPassword;
				this.allowLiveOrders = this.settingService.allowLiveOrders;
				this.allowRemoteOrders = this.settingService.allowRemoteOrders;
			}

		}
	}
</script>
