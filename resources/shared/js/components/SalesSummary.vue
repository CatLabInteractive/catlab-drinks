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
			<span v-if="event">{{event.name}} - </span>{{ $t('Sales summary') }}
		</h2>

		<summary-freshness :loaded-at="loadedAt" :refreshing="refreshing" @refresh="refreshNow" />

		<ul class="nav nav-tabs">
			<b-nav-item :to="{ name: 'summary', params: { id: this.eventId } }" :active="this.summaryType === 'items'">{{ $t('Sold items') }}</b-nav-item>
			<b-nav-item :to="{ name: 'summary-names', params: { id: this.eventId } }" :active="this.summaryType === 'names'">{{ $t('Orderer names') }}</b-nav-item>
		</ul>

		<div class="text-center" v-if="!firstLoadDone">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<div class="order-summary" v-if="summary">

			<table class="table table-striped">
				<thead>
					<tr>
						<th>{{ $t('Rank') }}</th>
						<th>{{ $t('Item') }}</th>
						<th>{{ $t('Amount') }}</th>
						<th>{{ $t('Price') }}</th>
						<th>{{ $t('VAT %') }}</th>
						<th class="text-right">{{ $t('Netto') }}</th>
						<th class="text-right">{{ $t('VAT') }}</th>
						<th class="text-right">{{ $t('Total') }}</th>
					</tr>
				</thead>

				<tbody>
					<template v-for="(productGroup, rootIndex) in groupedItems" :key="productGroup.menuItem ? productGroup.menuItem.id : rootIndex">
						<tr v-for="(product, index) in productGroup.sales" :key="index">
							<td>
								<span v-if="index === 0">{{ productGroup.rank }}</span>
							</td>
							<td>
								<span v-if="index === 0">
									<span v-if="product.menuItem">{{product.menuItem.name}}</span>

									<span v-if="productGroup.hasName && !hasDetails">{{ productGroup.visibleName }}</span>
									<button type="button" class="btn btn-secondary btn-sm" v-if="productGroup.hasName && hasDetails" @click="openDetails(product)">{{ productGroup.visibleName }}</button>
								</span>
							</td>
							<td>{{product.amount}}</td>
							<td><span v-if="product.menuItem">{{ $filters.money(product.price) }}</span></td>
							<td><span v-if="product.menuItem && product.vat_percentage">{{product.vat_percentage.toFixed(2)}}%</span></td>
							<td class="text-right"><span v-if="product.net_total">{{ $filters.money(product.net_total) }}</span></td>
							<td class="text-right"><span v-if="product.vat_total">{{ $filters.money(product.vat_total) }}</span></td>
							<td class="text-right">{{ $filters.money(product.totalSales) }}</td>
						</tr>
					</template>
				</tbody>

				<tfoot>
					<tr>
						<th>{{ $t('Total') }}</th>
						<th> </th>
						<th>{{summary.amount}}</th>
						<th>&nbsp;</th>
						<th>&nbsp;</th>
						<th class="text-right"><span v-if="summary.net_total">{{ $filters.money(summary.net_total) }}</span></th>
						<th class="text-right"><span v-if="summary.vat_total">{{ $filters.money(summary.vat_total) }}</span></th>
						<th class="text-right">{{ $filters.money(summary.totalSales) }}</th>
					</tr>
				</tfoot>
			</table>

		</div>


		<!-- Modal Component -->
		<b-modal ref="detailModal" :title="$t('Order details')" ok-only size="lg">
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
	import {AutoRefresh} from "../utils/autoRefresh";
	import SalesHistoryModal from "./SalesHistoryModal.vue";
	import SummaryFreshness from "./SummaryFreshness.vue";

	const ITEMS_REFRESH_MS = 30000;

	// The names tab can return thousands of rows at a large event.
	const NAMES_REFRESH_MS = 60000;

	/**
	 * Freezes the rows, so Vue does not make thousands of rows reactive. A refresh
	 * replaces the whole list.
	 */
	function freezeGroups(groups) {
		groups.forEach((group) => {
			group.sales.forEach(sale => Object.freeze(sale));
			Object.freeze(group.sales);
			Object.freeze(group);
		});
		return Object.freeze(groups);
	}

	export default {
		components: {
			SalesHistoryModal,
			SummaryFreshness,
		},

		props: [
			'eventId',
			'summaryType'
		],

		mounted() {

			this.eventService = new EventService(window.ORGANISATION_ID); // hacky hacky

		},

		unmounted() {

			this.destroyed = true;
			this.stopRefresh();

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
				summary: null,
				groupedItems: [],
				event: null,
				orderFilters: null,
				hasDetails: false,
				loadedAt: null,
				refreshing: false,
				firstLoadDone: false
			}
		},

		watch: {

			eventId(newVal) {

				this.stopRefresh();
				if (!newVal || this.destroyed) {
					return;
				}

				this.menuService = new MenuService(newVal);
				this.orderService = new OrderService(newVal);

				this.autoRefresh = new AutoRefresh(() => this.refresh(), {
					intervalMs: this.summaryType === 'names' ? NAMES_REFRESH_MS : ITEMS_REFRESH_MS,
					onChange: (running) => {
						this.refreshing = running;
					}
				});
				this.autoRefresh.start();

				return this.loadEvent();
			}

		},

		methods: {

			async loadEvent() {
				try {
					this.event = await this.eventService.get(this.eventId);

					// hacky hack hack
					window.document.title = this.event.name + ' - CatLab Drinks';
				} catch (e) {
					// The title is optional; the figures still load.
				}
			},

			stopRefresh() {
				if (this.autoRefresh) {
					this.autoRefresh.stop();
					this.autoRefresh = null;
				}
			},

			refreshNow() {
				if (this.autoRefresh) {
					this.autoRefresh.refresh();
				}
			},

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

			/**
			 * Loads the figures. On a failure it changes nothing, so the old rows and
			 * the old load time stay, and the page shows that they are old.
			 */
			async refresh() {

				try {
					const names = this.summaryType === 'names';
					const summary = names
						? await this.orderService.summaryNames({}, { background: true })
						: await this.orderService.summary({}, { background: true });

					const groupedItems = names ? this.groupByName(summary) : this.groupByMenuItem(summary);

					this.summary = Object.freeze(summary);
					this.groupedItems = freezeGroups(groupedItems);
					this.hasDetails = names;
					this.loadedAt = Date.now();
				} finally {
					this.firstLoadDone = true;
				}
			},

			groupByName(summary) {
				const groupedItems = summary.items.items.map(summaryLine => ({
					name: summaryLine.name,
					hasName: true,
					visibleName: summaryLine.name ? summaryLine.name : this.$t('Unknown / manual'),
					sales: [summaryLine]
				}));

				this.calculateRanks(groupedItems);

				return groupedItems;
			},

			groupByMenuItem(summary) {
				const groupedItems = [];
				const indexMap = {};
				let rank = 1;

				summary.items.items.forEach((summaryLine) => {
					const key = summaryLine.menuItem.id;

					if (typeof(indexMap[key]) === 'undefined') {
						indexMap[key] = groupedItems.push({
							menuItem: summaryLine.menuItem,
							rank: rank ++,
							sales: []
						}) - 1;
					}

					groupedItems[indexMap[key]].sales.push(summaryLine);
				});

				return groupedItems;
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
