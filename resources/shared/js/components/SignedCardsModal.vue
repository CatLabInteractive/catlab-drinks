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
		<!-- Signed cards list modal -->
		<b-modal ref="signedCardsModal" :title="modalTitle" ok-only size="lg">
			<div v-if="loading" class="text-center">
				<b-spinner />
			</div>
			<div v-if="!loading && cards.length === 0" class="alert alert-info">
				{{ $t('No signed cards found.') }}
			</div>
			<b-table v-if="!loading && cards.length > 0" striped hover :items="cards" :fields="fields">
				<template v-slot:cell(uid)="row">
					<a href="#" @click.prevent="showCardDetails(row.item)">{{ row.item.uid }}</a>
				</template>
				<template v-slot:cell(balance)="row">
					{{ formatBalance(row.item.balance) }}
				</template>
			</b-table>
		</b-modal>

		<!-- Card details modal -->
		<b-modal ref="cardModal" :title="$t('Card details')" ok-only size="lg">
			<div v-if="cardDetails">
				<card-details :cardUid="cardDetails.uid"></card-details>
			</div>
		</b-modal>
	</div>
</template>

<script>

	import CardDetails from "./CardDetails.vue";
	import {VisibleAmount} from "../nfccards/tools/VisibleAmount";

	export default {

		components: {
			'card-details': CardDetails,
		},

		data() {
			return {
				loading: false,
				cards: [],
				deviceName: '',
				cardDetails: null,
				fields: [
					{
						key: 'uid',
						label: this.$t('Card UID'),
					},
					{
						key: 'balance',
						label: this.$t('Balance'),
					}
				]
			}
		},

		computed: {
			modalTitle() {
				return this.$t('Cards signed by {name}:', { name: this.deviceName });
			}
		},

		methods: {

			async show(service, item) {
				this.deviceName = item.name;
				this.cards = [];
				this.loading = true;
				this.$refs.signedCardsModal.show();

				try {
					const result = await service.getSignedCards(item.id);
					this.cards = result.items;
				} catch (e) {
					this.cards = [];
				} finally {
					this.loading = false;
				}
			},

			formatBalance(balance) {
				return VisibleAmount.toVisible(balance);
			},

			showCardDetails(card) {
				this.cardDetails = card;
				this.$refs.cardModal.show();
			}
		}
	}
</script>
