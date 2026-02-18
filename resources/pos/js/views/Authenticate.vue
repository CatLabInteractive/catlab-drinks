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

	<div class="authenticate-container">
		<div class="authenticate-card">
			<h1 class="authenticate-title">
				<span>🔗</span>
				Connect Device
			</h1>
			<p class="authenticate-subtitle">Pair this device with your CatLab Drinks instance</p>

			<!-- Connecting state -->
			<div v-if="connecting" class="text-center py-4">
				<b-spinner label="Connecting..." />
				<p class="mt-3 text-muted">Connecting to server&hellip;</p>
			</div>

			<!-- Pairing code display -->
			<div v-else-if="showPairingCode" class="text-center py-4">
				<p class="text-muted mb-2">Enter this code in the management panel to pair this device:</p>
				<div class="pairing-code-display">{{ pairingCode }}</div>
				<p class="text-muted mt-3"><small>Waiting for confirmation&hellip;</small></p>
				<b-spinner small class="mt-1" />
			</div>

			<!-- Main options (when not scanning or entering token) -->
			<div v-else-if="!showScanner && !showTokenForm">
				<div class="authenticate-actions">
					<button class="btn btn-primary btn-lg btn-block mb-3" @click="showScannerView">
						<span class="mr-2">📷</span>
						Scan QR Code
					</button>
					<button class="btn btn-outline-secondary btn-block" @click="showManualEntry">
						<span class="mr-2">⌨️</span>
						Enter Token Manually
					</button>
				</div>
			</div>

			<!-- QR scanner -->
			<div v-else-if="showScanner">
				<qr-scanner @scanned="onQrScanned" @error="onQrError" />
				<div class="text-center mt-3">
					<button class="btn btn-outline-secondary" @click="showMainOptions">
						<span class="mr-1">←</span> Back
					</button>
				</div>
			</div>

			<!-- Manual token entry -->
			<div v-else-if="showTokenForm">
				<div class="form-group">
					<label for="tokenInput">Connection URL or Token</label>
					<input id="tokenInput" type="text" class="form-control" placeholder="Paste connection URL or token" v-model="token" />
				</div>
				<button class="btn btn-primary btn-block mb-3" @click="requestDeviceToken">
					<span class="mr-1">→</span> Authenticate
				</button>
				<div class="text-center">
					<button class="btn btn-outline-secondary" @click="showMainOptions">
						<span class="mr-1">←</span> Back
					</button>
				</div>
			</div>

			<div v-if="error" class="alert alert-danger mt-3" role="alert">
				<span class="mr-1">⚠️</span> {{ error }}
			</div>
		</div>
	</div>

</template>

<script type="ts">
import QrScanner from '../components/QrScanner.vue';
import { getDeviceUuid, setDeviceUuid, setAuthData } from '../../../shared/js/services/DeviceAuth';

export default {

	components: {
		'qr-scanner': QrScanner
	},

	data() {
		return {
			token: null,
			error: null,
			showTokenForm: false,
			showScanner: false,
			showPairingCode: false,
			connecting: false,
			pairingCode: null,
			pinger: null,
			device_uid: null
		};
	},

	mounted() {
		// Check for a connect token in the URL query parameter (from /connect page redirect)
		const urlParams = new URLSearchParams(window.location.search);
		const connectData = urlParams.get('connect');
		if (connectData) {
			// Remove the query parameter from the URL so it doesn't persist on reload
			window.history.replaceState({}, document.title, window.location.pathname);

			// Process the connect data
			this.token = connectData;
			this.requestDeviceToken();
		}
	},

	methods: {
		showMainOptions() {
			this.showScanner = false;
			this.showTokenForm = false;
			this.error = null;
		},

		showManualEntry() {
			this.showScanner = false;
			this.showTokenForm = true;
			this.error = null;
		},

		showScannerView() {
			this.showTokenForm = false;
			this.showScanner = true;
			this.error = null;
		},

		async onQrScanned(data) {
			this.showScanner = false;
			this.connecting = true;
			this.error = null;

			this.device_uid = await getDeviceUuid();

			this.requestAccessToken(data.api, data.token);
			this.pinger = setInterval(() => {
				this.requestAccessToken(data.api, data.token);
			}, 1000);
		},

		onQrError(message) {
			this.error = message;
		},

		async requestDeviceToken() {

			// Do we have a device id?
			this.device_uid = await getDeviceUuid();

			let data = null;

			// Check if the input is a full URL (from the connect URL field)
			try {
				const parsed = new URL(this.token);
				const dataParam = parsed.searchParams.get('data');
				if (dataParam) {
					const json = atob(dataParam);
					data = JSON.parse(json);
				}
			} catch (e) {
				// Not a URL, try as raw base64
			}

			// If not parsed from URL, try as raw base64
			if (!data) {
				try {
					let json = atob(this.token);
					data = JSON.parse(json);
				} catch (e) {
					this.error = 'Invalid token. Paste the connection URL or the base64 connect token.';
					return;
				}
			}

			const api = data.api;
			const token = data.token;

			if (!api || !token) {
				this.error = 'Invalid connection data: missing api or token.';
				return;
			}

			// Check if api appears valid
			if (!api.startsWith('http')) {
				this.error = 'Invalid API URL.';
				return;
			}

			this.showTokenForm = false;
			this.connecting = true;

			this.requestAccessToken(api, token);
			this.pinger = setInterval(() => {
				this.requestAccessToken(api, token);
			}, 1000);

		},

		async requestAccessToken(api, token) {

			let response = null;
			try {
				response = await axios.post(api + '/api/v1/device-connect.json', {
					token: token,
					device_uid: this.device_uid
				});
			} catch (e) {
				this.connecting = false;
				this.showTokenForm = true;
				clearInterval(this.pinger);
				return;
			}

			const responseData = response.data;

			// We should have received a device id.
			if (!this.device_uid) {
				if (responseData.device_uid) {
					this.device_uid = responseData.device_uid;
				} else {

					this.error = 'Invalid response...';
					this.connecting = false;
					this.showTokenForm = true;
					clearInterval(this.pinger);
					return;
				}
			}

			if (this.error) {
				clearInterval(this.pinger);
			}

			// Did we get an access token?
			if (responseData.access_token) {

				// We're done!

				// Clean up API for storage.
				const apiIdentifier = api.replace(/https?:\/\//, '');

				// Set the device id
				await setDeviceUuid(this.device_uid);

				// Store auth data
				await setAuthData(api, responseData.access_token, apiIdentifier);

				window.location.reload();

				return;

			}

			// We need to display a pairing code.
			this.connecting = false;
			this.showPairingCode = true;
			this.pairingCode = responseData.pairing_code;

		}
	}

}
</script>

<style scoped>
.authenticate-container {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 80vh;
	padding: 20px;
}

.authenticate-card {
	background: #fff;
	border-radius: 12px;
	box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
	padding: 40px;
	max-width: 480px;
	width: 100%;
}

.authenticate-title {
	text-align: center;
	font-size: 1.8rem;
	margin-bottom: 4px;
	color: #333;
}

.authenticate-subtitle {
	text-align: center;
	color: #888;
	margin-bottom: 30px;
}

.authenticate-actions {
	padding: 10px 0;
}

.pairing-code-display {
	font-size: 3rem;
	font-weight: 700;
	letter-spacing: 0.3em;
	color: #333;
	background: #f8f9fa;
	border: 2px dashed #dee2e6;
	border-radius: 8px;
	padding: 16px 24px;
	display: inline-block;
}
</style>
