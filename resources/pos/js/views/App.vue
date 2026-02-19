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
			<nfc-card-balance></nfc-card-balance>

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
				licenseStatus: null
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
		}
	}

</script>
