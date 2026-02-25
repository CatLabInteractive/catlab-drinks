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

	<div>

		<b-alert variant="warning" :show="showLicenseWarning" class="mb-0 rounded-0 text-center">
			<span class="mr-1">⚠️</span>
			<strong>{{ $t('No active license.') }}</strong>
			<span v-if="licenseStatus">
				{{ $t('{remaining} of {max} card scans remaining.', { remaining: licenseStatus.remainingCards, max: licenseStatus.maxCards }) }}
			</span>
			{{ $t('Please purchase a license in the management portal to remove this limit.') }}
		</b-alert>

		<b-navbar toggleable="lg">
			<b-navbar-brand href="#">CatLab Drinks</b-navbar-brand>
			<nfc-card-balance @showKeyModal="showKeyModal"></nfc-card-balance>

			<b-navbar-toggle target="nav_collapse" />

			<b-collapse is-nav id="nav_collapse">
				<b-navbar-nav>

					<b-nav-item :to="{ name: 'events' }"  v-if="!kioskMode">{{ $t('Events') }}</b-nav-item>

				</b-navbar-nav>

				<!-- Right aligned nav items -->
				<b-navbar-nav class="ml-auto"  v-if="!kioskMode">

					<b-navbar-nav>

						<b-nav-item :to="{ name: 'cards' }">{{ $t('Cards') }}</b-nav-item>
						<b-nav-item :to="{ name: 'settings' }">{{ $t('Settings') }}</b-nav-item>
						<language-toggle />

						<li class="nav-item">
							
						</li>

					</b-navbar-nav>
				</b-navbar-nav>
			</b-collapse>
		</b-navbar>

		<router-view></router-view>
		<payment-popup></payment-popup>

		<b-modal
			v-model="showLicenseErrorModal"
			:title="$t('License Required')"
			ok-only
			ok-variant="warning"
			ok-title="OK"
		>
			<p>
				<span class="text-danger mr-2">⚠️</span>
				{{ $t('Card limit exceeded. Please activate a license to continue scanning cards.') }}
			</p>
			<p class="text-muted">
				{{ $t('You can purchase and activate a license from the management portal under Devices.') }}
			</p>
		</b-modal>

		<!-- NFC Key Generation / Approval Modal -->
		<b-modal
			ref="keyModal"
			:title="$t('NFC Card Signing Credentials')"
			:no-close-on-backdrop="false"
			:no-close-on-esc="false"
			:hide-header-close="false"
			@hide="onKeyModalHide"
		>
			<!-- Status: No key generated -->
			<div v-if="keyModalStatus === 'none'">
				<div class="text-center mb-3">
					<span style="font-size: 3rem;">🔑</span>
				</div>
				<p>{{ $t('This device needs to generate signing credentials before it can read or write NFC cards.') }}</p>
				<p>{{ $t('After generating, your organisation administrator must approve the credentials before card operations are allowed.') }}</p>
				<p class="text-muted small">{{ $t('This is a security measure to prevent unauthorized devices from writing card data.') }}</p>

				<b-button variant="primary" block @click="generateCredentials" :disabled="generatingKey">
					<b-spinner small v-if="generatingKey" />
					<span v-else>🔐</span>
					{{ $t('Generate Credentials') }}
				</b-button>
			</div>

			<!-- Status: Pending approval -->
			<div v-if="keyModalStatus === 'pending'">
				<div class="text-center mb-3">
					<span style="font-size: 3rem;">⏳</span>
				</div>
				<b-alert variant="warning" show>
					<strong>{{ $t('Waiting for approval') }}</strong>
				</b-alert>
				<p>{{ $t('Your signing credentials have been generated and submitted to the server.') }}</p>
				<p>{{ $t('An organisation administrator must now approve this device\'s credentials in the management dashboard before card operations are allowed.') }}</p>
				<p class="text-muted small">{{ $t('The NFC indicator in the toolbar will turn green once the credentials are approved. You can close this dialog and continue using other features in the meantime.') }}</p>

				<b-button variant="outline-secondary" block @click="checkApprovalStatus" :disabled="checkingApproval">
					<b-spinner small v-if="checkingApproval" />
					<span v-else>🔄</span>
					{{ $t('Check Approval Status') }}
				</b-button>
			</div>

			<!-- Status: Revoked — allow generating new key -->
			<div v-if="keyModalStatus === 'revoked'">
				<div class="text-center mb-3">
					<span style="font-size: 3rem;">🚫</span>
				</div>
				<b-alert variant="danger" show>
					<strong>{{ $t('Credentials revoked') }}</strong>
				</b-alert>
				<p>{{ $t('This device\'s signing credentials have been revoked by an administrator. Card operations are disabled.') }}</p>
				<p>{{ $t('You can generate new credentials below. They will need to be approved again before card operations are allowed.') }}</p>

				<b-button variant="primary" block @click="generateCredentials" :disabled="generatingKey">
					<b-spinner small v-if="generatingKey" />
					<span v-else>🔐</span>
					{{ $t('Generate New Credentials') }}
				</b-button>
			</div>

			<!-- Status: Approved -->
			<div v-if="keyModalStatus === 'approved' && !nfcSpaceError">
				<div class="text-center mb-3">
					<span style="font-size: 3rem;">✅</span>
				</div>
				<b-alert variant="success" show>
					<strong>{{ $t('Credentials approved!') }}</strong>
				</b-alert>
				<p>{{ $t('Your signing credentials have been approved. This device can now read and write NFC cards.') }}</p>
			</div>

			<!-- NFC space limit error -->
			<div v-if="nfcSpaceError">
				<div class="text-center mb-3">
					<span style="font-size: 3rem;">⚠️</span>
				</div>
				<b-alert variant="danger" show>
					<strong>{{ $t('NFC Space Limit Exceeded') }}</strong>
				</b-alert>
				<p>{{ $t('The topup URL is too long to fit on the NFC card together with the signed card data.') }}</p>
				<p>{{ nfcSpaceError }}</p>
				<p class="text-muted small">{{ $t('Please configure a shorter topup domain in the organisation settings to resolve this issue.') }}</p>
			</div>

			<template #modal-footer>
				<b-btn variant="light" @click="$refs.keyModal.hide()">{{ $t('Close') }}</b-btn>
			</template>
		</b-modal>

	</div>

</template>
<script>

	import NfcCardBalance from '../../../shared/js/components/NfcCardBalance.vue';
	import PaymentPopup from '../../../shared/js/components/PaymentPopup.vue';
	import LanguageToggle from '../../../shared/js/components/LanguageToggle.vue';

	export default {

		components: {
			'payment-popup': PaymentPopup,
			'nfc-card-balance': NfcCardBalance,
			'language-toggle': LanguageToggle,
		},

		data() {
			return {
				kioskMode: false,
				showLicenseWarning: false,
				showLicenseErrorModal: false,
				licenseStatus: null,
				keyModalStatus: 'none',
				generatingKey: false,
				checkingApproval: false,
				nfcSpaceError: null
			}
		},

		unmounted() {
			this.eventListeners.forEach(e => e.unbind());
		},

		async mounted() {

			this.eventListeners = [];

			this.kioskMode = this.$kioskModeService.kioskModeActive;
			this.eventListeners.push(this.$kioskModeService.on('kioskmode:change', () => {
				this.kioskMode = this.$kioskModeService.kioskModeActive;
			}));

			// Listen for key status changes
			if (this.$cardService) {
				this.keyModalStatus = this.$cardService.getKeyStatus();

				this.eventListeners.push(this.$cardService.on('keyStatus:change', (status) => {
					this.keyModalStatus = status;
				}));

				this.eventListeners.push(this.$cardService.on('card:spaceError', (error) => {
					this.nfcSpaceError = error;
					this.$refs.keyModal.show();
				}));
			}

			// Check license status on Cordova
			if (typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.LicenseService) {
				try {
					const licenseService = new window.CATLAB_DRINKS_APP.LicenseService();
					this.licenseStatus = await licenseService.getLicenseStatus();
					if (!this.licenseStatus.valid) {
						this.showLicenseWarning = true;
					}
				} catch (e) {
					console.error('Failed to check license status:', e);
				}
			}

			// Listen for card errors (license errors)
			if (typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.nfc) {
				const nfc = window.CATLAB_DRINKS_APP.nfc;
				nfc.on('card:error', (error) => {
					if (window.CATLAB_DRINKS_APP.exceptions && error instanceof window.CATLAB_DRINKS_APP.exceptions.LicenseError) {
						this.showLicenseErrorModal = true;
					} else {
						console.error('NFC error:', error.message);
					}
				});
			}

			// Show key modal when navigating to the 'cards' page if no credentials
			this.$router.afterEach((to) => {
				if (to.name === 'cards' && this.$cardService && this.$cardService.hasCardReader && this.keyModalStatus !== 'approved') {
					this.$nextTick(() => {
						this.$refs.keyModal.show();
					});
				}
			});
		},

		methods: {
			showKeyModal() {
				this.$refs.keyModal.show();
			},

			async generateCredentials() {
				this.generatingKey = true;
				try {
					await this.$cardService.generateAndRegisterKey(
						window.DEVICE_UID,
						window.DEVICE_ID,
						window.DEVICE_SECRET
					);
					this.keyModalStatus = 'pending';
					this.$cardService.setKeyApprovalStatus('pending');
				} catch (e) {
					console.error('Failed to generate credentials:', e);
					alert(this.$t('Failed to generate credentials. Please try again.'));
				} finally {
					this.generatingKey = false;
				}
			},

			async checkApprovalStatus() {
				this.checkingApproval = true;
				try {
					const response = await axios.get('/pos-api/v1/devices/current');
					if (response.data.approved_at) {
						this.keyModalStatus = 'approved';
						this.$cardService.setKeyApprovalStatus('approved');
					} else if (!response.data.public_key) {
						// Key was revoked (removed from server)
						this.keyModalStatus = 'revoked';
						this.$cardService.setKeyApprovalStatus('none');
					}
				} catch (e) {
					console.error('Failed to check approval status:', e);
				} finally {
					this.checkingApproval = false;
				}
			},

			onKeyModalHide(e) {
				// Don't block the hide event
			}
		}
	}

</script>
