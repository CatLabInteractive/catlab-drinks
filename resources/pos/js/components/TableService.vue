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
		<b-tabs v-model="activeTab">

			<!-- Tables Tab -->
			<b-tab :title="$t('Tables')">
				<div class="text-center mt-3" v-if="!tablesLoaded">
					<b-spinner :label="$t('Loading data')" />
				</div>

				<div v-if="tablesLoaded">
					<b-row class="mt-3">
						<!-- No Table option -->
						<b-col cols="6" md="4" lg="3" xl="2" class="mb-3">
							<b-card
								class="text-center h-100 table-card"
								:class="{ 'border-primary': selectedTableId === 'none' }"
								@click="selectTable('none')"
								style="cursor: pointer"
							>
								<h5>{{ $t('No Table') }}</h5>
								<small class="text-muted">{{ $t('Unlinked patrons') }}</small>
							</b-card>
						</b-col>

						<!-- Table cards -->
						<b-col v-for="table in tables" :key="table.id" cols="6" md="4" lg="3" xl="2" class="mb-3">
							<b-card
								class="text-center h-100 table-card"
								:class="{ 'border-primary': selectedTableId === table.id }"
								@click="selectTable(table.id)"
								style="cursor: pointer"
							>
								<h5>{{ table.name }}</h5>
								<small class="text-muted">#{{ table.table_number }}</small>
							</b-card>
						</b-col>
					</b-row>
				</div>
			</b-tab>

			<!-- Order Queue Tab -->
			<b-tab :title="$t('Order Queue')">
				<div class="mt-3">
					<b-form-group>
						<b-form-checkbox v-model="filterMyOrders" inline>
							{{ $t('My orders only') }}
						</b-form-checkbox>
						<b-form-checkbox v-model="filterPreparedOnly" inline>
							{{ $t('Prepared only') }}
						</b-form-checkbox>
					</b-form-group>

					<div class="text-center" v-if="loadingOrders">
						<b-spinner :label="$t('Loading data')" />
					</div>

					<b-table v-if="!loadingOrders" striped hover :items="filteredOrders" :fields="orderFields">
						<template v-slot:cell(status)="row">
							<b-badge :variant="statusVariant(row.item.status)">
								{{ row.item.status }}
							</b-badge>
						</template>

						<template v-slot:cell(payment_status)="row">
							<b-badge :variant="paymentStatusVariant(row.item.payment_status)">
								{{ row.item.payment_status }}
							</b-badge>
						</template>

						<template v-slot:cell(actions)="row">
							<b-button
								v-if="row.item.status === 'pending'"
								size="sm"
								variant="info"
								@click="markPrepared(row.item)"
								class="mr-1"
							>
								{{ $t('Prepared') }}
							</b-button>
							<b-button
								v-if="row.item.status !== 'delivered' && row.item.status !== 'declined'"
								size="sm"
								variant="success"
								@click="markDelivered(row.item)"
								class="mr-1"
							>
								{{ $t('Delivered') }}
							</b-button>
							<b-button
								v-if="row.item.status !== 'declined'"
								size="sm"
								variant="danger"
								@click="markVoided(row.item)"
							>
								{{ $t('Void') }}
							</b-button>
						</template>
					</b-table>
				</div>
			</b-tab>
		</b-tabs>

		<!-- Table/Patron Modal: patron selection → patron details, all in one modal -->
		<b-modal ref="tableModal" size="xl" :title="modalTitle" hide-footer scrollable @hidden="onModalHidden">

			<!-- Step 1: Patron list (when no patron selected) -->
			<div v-if="!selectedPatron">
				<h4>
					{{ selectedTableId === 'none' ? $t('Unlinked Patrons') : $t('Patrons at {table}', { table: selectedTableName }) }}
				</h4>

				<div class="text-center" v-if="loadingPatrons">
					<b-spinner small />
				</div>

				<b-list-group v-if="!loadingPatrons" class="mb-3">
					<b-list-group-item
						v-for="patron in filteredPatrons"
						:key="patron.id"
						class="d-flex justify-content-between align-items-center"
						@click="selectPatron(patron)"
						style="cursor: pointer"
					>
						<div>
							<strong>{{ patron.name || ($t('Patron') + ' #' + patron.id) }}</strong>
							<b-badge v-if="patron.has_unpaid_orders" variant="warning" class="ml-2">
								{{ $t('Unpaid') }}
							</b-badge>
						</div>
						<div>
							<span v-if="patron.outstanding_balance > 0" class="text-danger font-weight-bold">
								&euro;{{ (patron.outstanding_balance).toFixed(2) }}
							</span>
						</div>
					</b-list-group-item>

					<b-list-group-item v-if="filteredPatrons.length === 0" class="text-muted text-center">
						{{ $t('No patrons at this table.') }}
					</b-list-group-item>
				</b-list-group>

				<b-button variant="success" @click="createPatron">
					&#xFF0B; {{ $t('New Patron') }}
				</b-button>
			</div>

			<!-- Step 2: Patron details (when patron selected) -->
			<div v-if="selectedPatron">
				<b-button variant="outline-secondary" size="sm" @click="backToPatronList" class="mb-3">
					&larr; {{ $t('Back to patron list') }}
				</b-button>

				<b-card class="mb-3" v-if="selectedPatron.outstanding_balance > 0">
					<b-row>
						<b-col>
							<h5>{{ $t('Outstanding Balance') }}</h5>
							<span class="h3 text-danger">&euro;{{ selectedPatron.outstanding_balance.toFixed(2) }}</span>
						</b-col>
						<b-col class="text-right" cols="auto">
							<b-button variant="success" size="lg" @click="settleBalance">
								{{ $t('Pay Outstanding Balance') }}
							</b-button>
						</b-col>
					</b-row>
				</b-card>

				<h4>{{ $t('Orders') }}</h4>
				<b-table striped hover :items="patronOrders" :fields="patronOrderFields" v-if="patronOrders.length > 0">
					<template v-slot:cell(items)="row">
						<span v-for="(item, index) in (row.item.order ? row.item.order.items : [])" :key="index">
							{{ item.amount }}&times; {{ item.name }}<span v-if="index < row.item.order.items.length - 1">, </span>
						</span>
					</template>

					<template v-slot:cell(status)="row">
						<b-badge :variant="statusVariant(row.item.status)">{{ row.item.status }}</b-badge>
					</template>

					<template v-slot:cell(payment_status)="row">
						<b-badge :variant="paymentStatusVariant(row.item.payment_status)">{{ row.item.payment_status }}</b-badge>
					</template>

					<template v-slot:cell(price)="row">
						&euro;{{ row.item.price ? row.item.price.toFixed(2) : '0.00' }}
					</template>
				</b-table>

				<b-alert v-if="patronOrders.length === 0" variant="info" show>
					{{ $t('No orders for this patron.') }}
				</b-alert>

				<hr />

				<!-- New Order: reuse LiveSales component -->
				<h4>{{ $t('New Order') }}</h4>
				<live-sales
					v-bind:event="event"
					:patron-id="selectedPatron.id"
					:table-id="selectedPatron.table_id"
					:allow-pay-later="event && event.allow_unpaid_table_orders"
					@order-created="onPatronOrderCreated"
				></live-sales>
			</div>
		</b-modal>

		<!-- Payment success modal -->
		<b-modal ref="processedModal" class="order-confirm-modal" ok-only button-size="lg" :title="$t('Payment successful')" ok-variant="success" no-close-on-backdrop>
			<p class="text-center"><span class="huge">👍</span></p>
			<p class="text-center alert alert-success">{{ $t('Payment processed successfully.') }}</p>
		</b-modal>
	</div>
</template>

<script>

	import {TableService as TableServiceApi} from "../../../shared/js/services/TableService";
	import {PatronService} from "../../../shared/js/services/PatronService";
	import {OrderService} from "../../../shared/js/services/OrderService";

	import LiveSales from '../../../shared/js/components/LiveSales.vue';

	export default {

		components: {
			'live-sales': LiveSales,
		},

		props: [
			'event'
		],

		data() {
			return {
				activeTab: 0,
				tablesLoaded: false,
				loadingPatrons: false,
				loadingOrders: false,
				tables: [],
				patrons: [],
				orders: [],
				selectedTableId: null,
				filterMyOrders: false,
				filterPreparedOnly: false,
				orderFields: [
					{ key: 'id', label: '#' },
					{ key: 'requester', label: this.$t('Requester') },
					{ key: 'status', label: this.$t('Status') },
					{ key: 'payment_status', label: this.$t('Payment') },
					{ key: 'date', label: this.$t('Date') },
					{ key: 'actions', label: this.$t('Actions'), class: 'text-right' }
				],

				// Patron modal data
				selectedPatron: null,
				patronOrders: [],
				patronOrderFields: [
					{ key: 'id', label: '#' },
					{ key: 'items', label: this.$t('Items') },
					{ key: 'status', label: this.$t('Status') },
					{ key: 'payment_status', label: this.$t('Payment') },
					{ key: 'price', label: this.$t('Price') },
					{ key: 'date', label: this.$t('Date') }
				],
			}
		},

		computed: {
			selectedTableName() {
				if (this.selectedTableId === 'none') return this.$t('No Table');
				const table = this.tables.find(t => t.id === this.selectedTableId);
				return table ? table.name : '';
			},

			filteredPatrons() {
				if (this.selectedTableId === 'none') {
					return this.patrons.filter(p => !p.table_id);
				}
				return this.patrons.filter(p => p.table_id === this.selectedTableId);
			},

			filteredOrders() {
				let orders = this.orders.filter(o =>
					o.status === 'pending' || o.status === 'prepared'
				);

				if (this.filterMyOrders && window.DEVICE_ID) {
					orders = orders.filter(o => o.assigned_device_id === window.DEVICE_ID);
				}

				if (this.filterPreparedOnly) {
					orders = orders.filter(o => o.status === 'prepared');
				}

				return orders;
			},

			modalTitle() {
				if (this.selectedPatron) {
					return this.selectedPatron.name || (this.$t('Patron') + ' #' + this.selectedPatron.id);
				}
				return this.selectedTableName;
			}
		},

		watch: {
			event(newVal) {
				if (newVal) {
					this.loadTableData();
				}
			},

			activeTab(newTab) {
				if (newTab === 1) {
					this.refreshOrders();
				}
			}
		},

		mounted() {
			if (this.event) {
				this.loadTableData();
			}
		},

		methods: {

			async loadTableData() {
				this.tableService = new TableServiceApi(this.event.id);
				this.patronService = new PatronService(this.event.id);
				this.orderService = new OrderService(this.event.id);

				this.tables = (await this.tableService.index({ sort: 'table_number' })).items;
				await this.refreshPatrons();
				this.tablesLoaded = true;
			},

			async refreshPatrons() {
				this.loadingPatrons = true;
				this.patrons = (await this.patronService.index()).items;
				this.loadingPatrons = false;
			},

			async refreshOrders() {
				this.loadingOrders = true;
				this.orders = (await this.orderService.index({
					status: 'pending,prepared'
				})).items;
				this.loadingOrders = false;
			},

			selectTable(tableId) {
				this.selectedTableId = tableId;
				this.selectedPatron = null;
				this.$refs.tableModal.show();
			},

			async selectPatron(patron) {
				this.selectedPatron = patron;
				this.patronOrders = (await this.orderService.index({ patron_id: patron.id })).items;
			},

			async createPatron() {
				const patron = {
					name: null,
					table_id: this.selectedTableId === 'none' ? null : this.selectedTableId
				};
				const newPatron = await this.patronService.create(patron);
				await this.refreshPatrons();

				// Immediately select the new patron
				await this.selectPatron(newPatron);
			},

			backToPatronList() {
				this.selectedPatron = null;
			},

			onModalHidden() {
				this.selectedPatron = null;
			},

			async onPatronOrderCreated() {
				// Refresh patron data after order is placed via LiveSales
				this.selectedPatron = await this.patronService.get(this.selectedPatron.id);
				this.patronOrders = (await this.orderService.index({ patron_id: this.selectedPatron.id })).items;
				await this.refreshPatrons();
			},

			async settleBalance() {
				const unpaidOrders = this.patronOrders.filter(o => o.payment_status === 'unpaid');

				if (unpaidOrders.length === 0) return;

				try {
					let paymentData = await this.$paymentService.orders(unpaidOrders);

					// Mark orders as paid on the server
					await Promise.all(
						unpaidOrders.map(order =>
							this.orderService.update(order.id, {
								payment_status: 'paid',
								payment_type: paymentData.paymentType || null
							})
						)
					);

					this.$refs.processedModal.show();
					setTimeout(() => {
						if (this.$refs.processedModal) {
							this.$refs.processedModal.hide();
						}
					}, 2000);

					// Refresh patron data
					this.selectedPatron = await this.patronService.get(this.selectedPatron.id);
					this.patronOrders = (await this.orderService.index({ patron_id: this.selectedPatron.id })).items;
					await this.refreshPatrons();

				} catch (e) {
					// Payment was cancelled
					console.log('Payment cancelled or failed:', e);
				}
			},

			// --- Order queue actions ---

			async markPrepared(order) {
				await this.orderService.update(order.id, { status: 'prepared' });
				await this.refreshOrders();
			},

			async markDelivered(order) {
				await this.orderService.update(order.id, { status: 'delivered' });
				await this.refreshOrders();
			},

			async markVoided(order) {
				if (confirm(this.$t('Are you sure you want to void this order?'))) {
					await this.orderService.update(order.id, {
						status: 'declined',
						payment_status: 'voided'
					});
					await this.refreshOrders();
				}
			},

			// --- Status helpers ---

			statusVariant(status) {
				switch (status) {
					case 'pending': return 'warning';
					case 'prepared': return 'info';
					case 'delivered': return 'success';
					case 'declined': return 'danger';
					default: return 'secondary';
				}
			},

			paymentStatusVariant(status) {
				switch (status) {
					case 'unpaid': return 'warning';
					case 'paid': return 'success';
					case 'voided': return 'danger';
					default: return 'secondary';
				}
			}
		}
	}
</script>
