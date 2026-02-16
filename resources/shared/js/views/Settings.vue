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
			<b-spinner label="Loading data" />
		</div>

		<b-row v-if="loaded">

			<b-col cols="6">

				<h2>Point of sale settings</h2>

				<b-form @submit="onSubmit" @reset="onReset">

					<b-form-fieldset>
						<legend>General settings</legend>

						<b-form-group
							id="terminal-name-group"
							label="Terminal name"
							label-for="terminal-name"
							description="Describing name of this specific terminal."
						>
							<b-form-input
								id="terminal-name"
								v-model="name"
								type="text"
								required
								placeholder="Terminal name"
							></b-form-input>
						</b-form-group>

						<b-form-group
							id="allow_live_orders"
							description="This terminal can process orders at the bar"
						>
							<label>
								<input type="checkbox" v-model="allowLiveOrders"></input>
								Allow live orders at this terminal<br />
							</label>
						</b-form-group>

						<b-form-group
							id="allow_remote_orders"
							description="This terminal can process orders from tables"
						>
							<label>
								<input type="checkbox" v-model="allowRemoteOrders"></input>
								Allow remote orders at this terminal<br />
							</label>
						</b-form-group>

					</b-form-fieldset>

					<b-form-fieldset>
						<legend>Cashless system</legend>

						<b-form-group
							id="nfc-server-group"
							label="NFC webserver url"
							label-for="nfc-server"
						>
							<b-form-input
								id="nfc-server"
								v-model="nfcServer"
								type="text"
								placeholder="NFC Server url"
							></b-form-input>
						</b-form-group>

						<b-form-group
							id="nfc-password-group"
							label="NFC webserver password"
							label-for="nfc-server"
						>
							<b-form-input
								id="nfc-password"
								v-model="nfcPassword"
								type="text"
								placeholder="NFC Server password"
							></b-form-input>
						</b-form-group>

					</b-form-fieldset>

					<b-button type="submit" variant="primary">Save</b-button>
					<b-button type="reset" variant="danger">Reset</b-button>
				</b-form>

				<hr />

				<b-form-fieldset>
					<legend>Device</legend>
					<p class="text-muted">Disconnect this device from the server. You will need to re-pair it to use it again.</p>
					<b-button variant="outline-danger" @click="logout">
						<i class="fas fa-sign-out-alt mr-1"></i> Logout
					</b-button>
				</b-form-fieldset>

			</b-col>
		</b-row>

	</b-container>

</template>

<script>

	export default {

		props: [

		],


		async mounted() {

			this.settingService = this.$settingService;

			this.settingService.load()
				.then(function() {
					this.onReset();
					this.loaded = true;
				}.bind(this));
		},

		data() {
			return {
				loaded: false,
				name: '',
				nfcServer: '',
				nfcPassword: '',

				allowLiveOrders: false,
				allowRemoteOrders: false
			}
		},

		watch: {



		},

		methods: {

			logout() {
				if (confirm('Are you sure you want to logout? This device will need to be re-paired to connect again.')) {
					const apiIdentifier = window.localStorage.getItem('calab_drinks_pos_api_identifier');
					window.localStorage.removeItem('catlab_drinks_device_pos_uid');
					window.localStorage.removeItem('calab_drinks_pos_api_identifier');
					if (apiIdentifier) {
						window.localStorage.removeItem('catlab_drinks_pos_api_url[' + apiIdentifier + ']');
						window.localStorage.removeItem('catlab_drinks_pos_access_token[' + apiIdentifier + ']');
					}
					window.location.reload();
				}
			},

			onSubmit(evt) {
				evt.preventDefault();

				this.settingService.terminalName = this.name;
				this.settingService.nfcServer = this.nfcServer;
				this.settingService.nfcPassword = this.nfcPassword;

				this.settingService.allowLiveOrders = this.allowLiveOrders;
				this.settingService.allowRemoteOrders = this.allowRemoteOrders;

				this.settingService.save()
					.then(function() {
						window.location.reload();
					})

			},

			onSubmitOrgSettings(evt) {
				evt.preventDefault();
			},

			onReset(evt = null) {
				if (evt) {
					evt.preventDefault();
				}

				this.name = this.settingService.terminalName;
				this.nfcServer = this.settingService.nfcServer;
				this.nfcPassword = this.settingService.nfcPassword;
				this.allowLiveOrders = this.settingService.allowLiveOrders;
				this.allowRemoteOrders = this.settingService.allowRemoteOrders;
			},

			onResetOrgSettings(evt = null) {
				if (evt) {
					evt.preventDefault();
				}
			}

		}
	}
</script>
