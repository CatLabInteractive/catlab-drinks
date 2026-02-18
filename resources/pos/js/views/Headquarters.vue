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

	<b-container fluid v-if="event">

		<div v-if="!showRemoteOrders && !showLiveOrders">
			<b-alert variant="danger" :show="true">
				{{ $t('You have disabled both live and remote orders.') }}<br />
				{{ $t('This terminal will not be able to process any orders.') }}<br />
				{{ $t('Please enable either live or remote orders in the settings.') }}
			</b-alert>
		</div>

		<b-row>
			<b-col v-if="showLiveOrders" :cols="showRemoteOrders ? 8 : 12" id="live-orders">

				<live-sales v-bind:event="event"></live-sales>

			</b-col>

			<b-col v-if="showRemoteOrders" :cols="showLiveOrders ? 4 : 12" class="remote-orders">

				<remote-orders v-bind:event="event"></remote-orders>

			</b-col>
		</b-row>

	</b-container>

</template>

<script>

	import {EventService} from "../../../shared/js/services/EventService";

	import LiveSales from '../../../shared/js/components/LiveSales.vue';
	import RemoteOrders from '../../../shared/js/components/RemoteOrders.vue';

	export default {

		components: {
			'live-sales': LiveSales,
			'remote-orders': RemoteOrders,
		},

		async mounted() {
			this.eventService = new EventService(window.ORGANISATION_ID); // hacky hacky
			await this.refresh();
		},

		data() {
			return {
				event: null,
				showLiveOrders: this.$settingService.allowLiveOrders,
				showRemoteOrders: this.$settingService.allowRemoteOrders,
			}
		},

		watch: {
			'$route' (to, from) {
				// react to route changes...
				this.eventId = to.params.id;
				this.refresh();
			}
		},

		methods: {

			async refresh() {

				this.eventId = this.$route.params.id;

				//this.$kioskModeService.enableKioskMode();

				this.event = await this.eventService.get(this.eventId);

				// also set all payment possibilities
				this.$paymentService.setPaymentMethods(
					this.event.payment_cards,
					this.event.payment_cash,
					this.event.payment_vouchers,
					this.event.payment_voucher_value
				);

			}
		}
	}
</script>
