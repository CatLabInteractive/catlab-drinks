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

		<div v-if="!loading">

			<div v-if="success">
				<p class="alert alert-success">Topup succesful.</p>
			</div>

			<div v-if="showConfirmation">
				<ul>
					<li>Amount: {{ amount }}</li>
					<li>Reason: {{ reason }}</li>
					<li>
						<button class="btn btn-success" v-on:click="confirmTopup()">Confirm</button>
						<button class="btn btn-danger" v-on:click="cancelTopup()">Cancel</button>
					</li>
				</ul>
			</div>

			<div v-if="showForm">
				<div class="form-group">
					<label for="amount">Amount</label>
					<input type="number" class="form-control" id="amount" placeholder="Topup amount" step="0.01" v-model="amount">
				</div>

				<div class="form-group">
					<label for="reason">Reason</label>
					<input type="text" class="form-control" id="reason" placeholder="Specify a reason for the topup." v-model="reason">
				</div>

				<button class="btn btn-success" v-on:click="topup()">Topup</button>
			</div>
		</div>
	</div>

</template>

<script>

	import {MenuService} from "../services/MenuService";
	import {OrderService} from "../services/OrderService";
	import {TopupService} from "../services/TopupService";

	export default {

		props: [
			'card'
		],

		watch: {

			card(newVal, oldVal) {
				this.topupService = new TopupService(newVal.id);
			}

		},

		mounted() {

			if (this.card) {
				this.topupService = new TopupService(this.card.id);
			}

		},

		unmounted() {

		},

		data() {
			return {
				loading: false,
				showConfirmation: false,
				showForm: true,
				success: false,
				amount: 0,
				reason: ''
			}
		},

		methods: {
			topup: function() {
				this.showConfirmation = true;
				this.showForm = false;
			},

			confirmTopup: async function() {
				this.loading = true;
				this.showConfirmation = false;
				this.showForm = false;

				await this.topupService.create({
					amount: parseFloat(this.amount),
					reason: this.reason
				});

				this.loading = false;
				this.showForm = true;

				this.success = true;
				setTimeout(() => {
					this.success = false;
				}, 2000);
			},

			cancelTopup: function() {
				this.amount = 0;
				this.reason = '';

				this.loading = false;
				this.showConfirmation = false;
				this.showForm = true;
			}
		}
	}
</script>
