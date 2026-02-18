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
		<div v-if="loading"><b-spinner /></div>
		<div v-if="card">
			<table class="table">

				<tbody>
					<tr>
						<td>{{ $t('ID') }}</td>
						<td>{{ card.id }}</td>
					</tr>

					<tr v-if="card.name">
						<td>{{ $t('Name') }}</td>
						<td>{{ card.name }}</td>
					</tr>

					<tr>
						<td>{{ $t('Balance') }}</td>
						<td>{{ VisibleAmount.toVisible(card.balance) }}</td>
					</tr>

					<tr v-if="card.discount !== 0">
						<td>{{ $t('Discount') }}</td>
						<td>{{ card.discount }}</td>
					</tr>
				</tbody>

			</table>

			<h3>{{ $t('Topup') }}</h3>
			<card-topup :card="card" />
		</div>
	</div>

</template>

<script>

	import {VisibleAmount} from "../nfccards/tools/VisibleAmount";
	import CardTopup from "./Topup.vue";

	export default {

		components: {
			'card-topup': CardTopup,
		},

		props: [
			'cardUid'
		],

		mounted() {
			this.loading = true;
			this.card = null;
			this.loadCardData();
		},

		watch: {

		},

		data() {
			return {
				card: null,
				loading: false,
				VisibleAmount: VisibleAmount
			}
		},

		methods: {

			async loadCardData() {
				this.card = await this.$cardService.getCardFromUid(this.cardUid);
				this.loading = false;
			},

			topup() {
				alert(this.$t('Topping up'));
			}

		}
	}
</script>
