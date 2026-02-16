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
		<h2>
			<span v-if="event">{{event.name}} - </span>Sales summary
		</h2>

		<ul class="nav nav-tabs">
			<b-nav-item :to="{ name: 'summary', params: { id: this.eventId } }" :active="this.summaryType === 'items'">Sold items</b-nav-item>
			<b-nav-item :to="{ name: 'summary-names', params: { id: this.eventId } }" :active="this.summaryType === 'names'">Orderer names</b-nav-item>
		</ul>

		<div class="text-center" v-if="!loaded">
			<b-spinner label="Loading data" />
		</div>

		<div class="order-summary" v-if="summary">

			<table class="table table-striped">
				<thead>
					<tr>
						<th>Rank</th>
						<th>Item</th>
						<th>Amount</th>
						<th>Price</th>
						<th>VAT %</th>
						<th class="text-right">Netto</th>
						<th class="text-right">VAT</th>
						<th class="text-right">Total</th>
					</tr>
				</thead>

				<tbody>
					<template v-for="(productGroup, rootIndex) in groupedItems">
						<tr v-for="(product, index) in productGroup.sales">
							<td>
								<span v-if="index === 0">{{ productGroup.rank }}</span>
							</td>
							<td>
								<span v-if="index === 0">
									<span v-if="product.menuItem">{{product.menuItem.name}}</span>
									<b-btn size="sm" v-if="product.menuItem && hasDetails" @click="openDetails(product)">{{product.menuItem.name}}</b-btn>

									<span v-if="productGroup.hasName && !hasDetails">{{ productGroup.visibleName }}</span>
									<b-btn size="sm" v-if="productGroup.hasName && hasDetails" @click="openDetails(product)">{{ productGroup.visibleName }}</b-btn>
								</span>
							</td>
							<td>{{product.amount}}</td>
							<td><span v-if="product.menuItem">€{{product.price.toFixed(2)}}</span></td>
							<td><span v-if="product.menuItem && product.vat_percentage">{{product.vat_percentage.toFixed(2)}}%</span></td>
							<td class="text-right"><span v-if="product.net_total">€{{product.net_total.toFixed(2)}}</span></td>
							<td class="text-right"><span v-if="product.vat_total">€{{product.vat_total.toFixed(2)}}</span></td>
							<td class="text-right">€{{product.totalSales.toFixed(2)}}</td>
						</tr>
					</template>
				</tbody>

				<tfoot>
					<tr>
						<th>Total</th>
						<th> </th>
						<th>{{summary.amount}}</th>
						<th>&nbsp;</th>
						<th>&nbsp;</th>
						<th class="text-right"><span v-if="summary.net_total">€{{summary.net_total.toFixed(2)}}</span></th>
						<th class="text-right"><span v-if="summary.vat_total">€{{summary.vat_total.toFixed(2)}}</span></th>
						<th class="text-right">€{{summary.totalSales.toFixed(2)}}</th>
					</tr>
				</tfoot>
			</table>

		</div>


		<!-- Modal Component -->
		<b-modal ref="detailModal" title="Order details" ok-only size="lg">
			<div v-if="orderFilters">
				<sales-history-modal :filter="orderFilters" :eventId="eventId"></sales-history-modal>
			</div>
		</b-modal>

	</div>


</template>

<script>

	import {MenuService} from "../services/MenuService";
	import {OrderService} from "../services/OrderService";
	import {EventService} from "../services/EventService";
	import SalesHistoryModal from "./SalesHistoryModal.vue";

	export default {
		components: {
			SalesHistoryModal,
		},

		props: [
			'eventId',
			'summaryType'
		],

		mounted() {

			this.eventService = new EventService(window.ORGANISATION_ID); // hacky hacky

		},

		beforeDestroy() {
			if (this.interval) {
				clearInterval(this.interval);
			}
		},

		unmounted() {

			if (this.orderService) {
				this.orderService.destroy();
			}

			if (this.menuService) {
				this.menuService.destroy();
			}

			if (this.eventService) {
				this.eventService.destroy();
			}

		},

		data() {
			return {
				loaded: false,
				summary: null,
				groupedItems: [],
				event: null,
				orderFilters: null,
				hasDetails: false
			}
		},

		watch: {

			async eventId(newVal, oldVal) {

				this.menuService = new MenuService(newVal);
				this.orderService = new OrderService(newVal);

				if (this.interval) {
					clearInterval(this.interval);
				}

				this.event = await this.eventService.get(this.eventId);

				// hacky hack hack
				window.document.title = this.event.name + ' - CatLab Drinks';

				await this.refresh();
				this.interval = setInterval(
					() => {
						//this.refresh();
					},
					30000
				);
			}

		},

		methods: {

			openDetails(row) {

				switch (this.summaryType) {
					case 'names':
						this.orderFilters = {
							requester: row.name
						};
						break;

					default:
						return;
				}

				this.$refs.detailModal.show();
			},

			async refresh() {

				this.loaded = true;

				switch (this.summaryType) {
					case 'names':
						this.summary = (await this.orderService.summaryNames({}));

						this.groupedItems = [];

						this.summary.items.items.forEach(
							(summaryLine) => {
								this.groupedItems.push({
									name: summaryLine.name,
									hasName: true,
									visibleName: summaryLine.name ? summaryLine.name : 'Unknown / manual',
									sales: [summaryLine]
								})
							}
						);

						this.calculateRanks(this.groupedItems);

						this.hasDetails = true;

						break;

					default:
						this.summary = (await this.orderService.summary({}));

						this.groupedItems = [];

						let indexMap = {};
						var rank = 1;
						this.summary.items.items.forEach(
							(summaryLine) => {

								const key = summaryLine.menuItem.id;

								if (typeof(indexMap[key]) === 'undefined') {
									indexMap[key] = this.groupedItems.push({
										menuItem: summaryLine.menuItem,
										rank: rank ++,
										sales: []
									}) - 1;
								}

								this.groupedItems[indexMap[key]].sales.push(summaryLine);
							}
						);

						break;
				}
			},

			calculateRanks(groupedItems) {

				var filtered = groupedItems.filter(a => {
					return a.name;
				});

				const sortedOnRanks = filtered.sort(function(a, b) {
					let totalA = 0;
					a.sales.forEach(sale => {
						totalA += sale.totalSales;
					})

					let totalB = 0;
					b.sales.forEach(sale => {
						totalB += sale.totalSales;
					});

					return totalB - totalA;
				});

				let index = 0;
				sortedOnRanks.forEach(item => {
					item.rank = ++index;
				});
			}
		}
	}
</script>
