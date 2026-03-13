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

		<div v-if="!showRemoteOrders && !showLiveOrders && !showTableService">
			<b-alert variant="danger" :show="true">
				{{ $t('You have disabled both live and remote orders.') }}<br />
				{{ $t('This terminal will not be able to process any orders.') }}<br />
				{{ $t('Please enable either live or remote orders in the settings.') }}
			</b-alert>
		</div>

		<!-- Standard bar POS mode -->
		<b-row v-if="!showTableService">
			<b-col v-if="showLiveOrders" :cols="showRemoteOrders ? 8 : 12" id="live-orders">

				<live-sales v-bind:event="event"></live-sales>

			</b-col>

			<b-col v-if="showRemoteOrders" :cols="showLiveOrders ? 4 : 12" class="remote-orders">

				<remote-orders v-bind:event="event" v-bind:deviceId="deviceId" v-bind:initialCategoryFilter="deviceCategoryFilterId"></remote-orders>

			</b-col>
		</b-row>

		<!-- Table Service mode (waiter dashboard) -->
		<div v-if="showTableService">
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

						<!-- Selected table: show patrons -->
						<div v-if="selectedTableId !== null" class="mt-3">
							<h3>
								{{ selectedTableId === 'none' ? $t('Unlinked Patrons') : $t('Patrons at {table}', { table: selectedTableName }) }}
								<b-button size="sm" variant="success" @click="createPatron" class="ml-2">
									&#xFF0B; {{ $t('New Patron') }}
								</b-button>
							</h3>

							<div class="text-center" v-if="loadingPatrons">
								<b-spinner small />
							</div>

							<b-list-group v-if="!loadingPatrons">
								<b-list-group-item
									v-for="patron in filteredPatrons"
									:key="patron.id"
									class="d-flex justify-content-between align-items-center"
									@click="openPatronModal(patron)"
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
						</div>
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
		</div>

		<!-- Patron Detail Modal -->
		<b-modal ref="patronModal" size="xl" :title="patronModalTitle" hide-footer scrollable>
			<div v-if="selectedPatron">
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

				<!-- New Order form for this patron -->
				<h4>{{ $t('New Order') }}</h4>
				<div class="text-center" v-if="!patronMenuLoaded">
					<b-spinner small :label="$t('Loading data')" />
				</div>

				<div v-if="patronMenuLoaded">
					<div class="live-orders">
						<div v-for="(item, index) in patronMenuItems" class="product" v-on:click="increasePatronOrder(item, index, $event)">
							<span class="name">{{item.name}}</span>
							<span class="buttons">
								<button class="btn btn-danger btn-sm" v-on:click="decreasePatronOrder(item, index, $event)">
									<span>&minus;</span>
								</button>
							</span>
							<span class="amount">{{item.amount}}</span>
						</div>
					</div>

					<div class="total mt-2">
						<p>{{ $t('Total: {amount} items = €{price}', { amount: patronOrderTotals.amount, price: patronOrderTotals.price.toFixed(2) }) }}</p>
					</div>

					<b-button variant="success" size="lg" @click="submitPatronOrder" :disabled="patronOrderTotals.amount === 0 || patronOrderSaving">
						<b-spinner small v-if="patronOrderSaving" class="mr-1"></b-spinner>
						{{ $t('Place Order') }}
					</b-button>

					<b-alert v-if="patronOrderWarning" variant="danger" :show="true" class="mt-2">
						{{ patronOrderWarning }}
					</b-alert>
				</div>
			</div>
		</b-modal>

		<!-- Payment success modal -->
		<b-modal ref="processedModal" class="order-confirm-modal" ok-only button-size="lg" :title="$t('Payment successful')" ok-variant="success" no-close-on-backdrop>
			<p class="text-center"><span class="huge">👍</span></p>
			<p class="text-center alert alert-success">{{ $t('Payment processed successfully.') }}</p>
		</b-modal>

		<!-- Payment popup (shared) -->
		<payment-popup></payment-popup>

	</b-container>

</template>

<script>

	import {EventService} from "../../../shared/js/services/EventService";
	import {TableService} from "../../../shared/js/services/TableService";
	import {PatronService} from "../../../shared/js/services/PatronService";
	import {OrderService} from "../../../shared/js/services/OrderService";
	import {MenuService} from "../../../shared/js/services/MenuService";

	import LiveSales from '../../../shared/js/components/LiveSales.vue';
	import RemoteOrders from '../../../shared/js/components/RemoteOrders.vue';
	import PaymentPopup from '../../../shared/js/components/PaymentPopup.vue';

	import { toRaw } from 'vue';

	export default {

		components: {
			'live-sales': LiveSales,
			'remote-orders': RemoteOrders,
			'payment-popup': PaymentPopup,
		},

		async mounted() {
			this.eventService = new EventService(window.ORGANISATION_ID); // hacky hacky
			await this.refresh();
		},

		data() {
			return {
				event: null,
				deviceId: window.DEVICE_ID || null,
				deviceCategoryFilterId: window.DEVICE_CATEGORY_FILTER_ID || null,
				showLiveOrders: this.$settingService.allowLiveOrders,
				showRemoteOrders: this.$settingService.allowRemoteOrders,
				showTableService: this.$settingService.allowTableService,

				// Table service data
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

				// New order in patron modal
				patronMenuLoaded: false,
				patronMenuItems: [],
				patronOrderTotals: { amount: 0, price: 0 },
				patronOrderSaving: false,
				patronOrderWarning: null,
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

			patronModalTitle() {
				if (!this.selectedPatron) return '';
				return this.selectedPatron.name || (this.$t('Patron') + ' #' + this.selectedPatron.id);
			}
		},

		watch: {
			'$route' (to, from) {
				// react to route changes...
				this.eventId = to.params.id;
				this.refresh();
			},

			activeTab(newTab) {
				if (newTab === 1) {
					this.refreshOrders();
				}
			}
		},

		methods: {

			async refresh() {

				this.eventId = this.$route.params.id;

				this.event = await this.eventService.get(this.eventId);

				// also set all payment possibilities
				this.$paymentService.setPaymentMethods(
					this.event.payment_cards,
					this.event.payment_cash,
					this.event.payment_vouchers,
					this.event.payment_voucher_value
				);

				// If table service mode, load tables and patrons
				if (this.showTableService) {
					this.tableService = new TableService(this.eventId);
					this.patronService = new PatronService(this.eventId);
					this.orderService = new OrderService(this.eventId);
					this.menuService = new MenuService(this.eventId);

					this.tables = (await this.tableService.index({ sort: 'table_number' })).items;
					await this.refreshPatrons();
					this.tablesLoaded = true;
				}
			},

			// --- Table service methods ---

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
			},

			async createPatron() {
				const patron = {
					name: null,
					table_id: this.selectedTableId === 'none' ? null : this.selectedTableId
				};
				await this.patronService.create(patron);
				await this.refreshPatrons();
			},

			async openPatronModal(patron) {
				this.selectedPatron = patron;
				this.patronOrders = (await this.orderService.index({ patron_id: patron.id })).items;

				// Load menu for new orders
				this.patronMenuLoaded = false;
				this.patronMenuItems = (await this.menuService.index()).items;
				this.patronMenuItems.forEach(item => { item.amount = 0; });
				this.updatePatronOrderTotals();
				this.patronMenuLoaded = true;

				this.$refs.patronModal.show();
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

			// --- Patron new order methods ---

			increasePatronOrder(product, index, event) {
				if (event) {
					event.preventDefault();
					event.stopPropagation();
				}
				product.amount++;
				this.patronMenuItems.splice(index, 1, product);
				this.updatePatronOrderTotals();
			},

			decreasePatronOrder(product, index, event) {
				if (event) {
					event.preventDefault();
					event.stopPropagation();
				}
				product.amount--;
				if (product.amount < 0) product.amount = 0;
				this.patronMenuItems.splice(index, 1, product);
				this.updatePatronOrderTotals();
			},

			updatePatronOrderTotals() {
				let totalPrice = 0;
				let totalAmount = 0;
				this.patronMenuItems.forEach(item => {
					if (item.isTotals) return;
					totalPrice += item.amount * item.price;
					totalAmount += item.amount;
				});
				this.patronOrderTotals = { amount: totalAmount, price: totalPrice };
			},

			async submitPatronOrder() {
				const selectedItems = [];
				this.patronMenuItems.forEach(item => {
					if (item.amount > 0) {
						selectedItems.push({
							menuItem: { id: item.id, name: item.name },
							amount: item.amount,
							price: item.price
						});
					}
				});

				if (selectedItems.length === 0) return;

				this.patronOrderSaving = true;
				this.patronOrderWarning = null;

				try {
					const data = {
						location: 'Table Service',
						status: 'pending',
						paid: false,
						payment_status: this.event.allow_unpaid_table_orders ? 'unpaid' : 'paid',
						price: this.patronOrderTotals.price,
						discount: 0,
						patron_id: this.selectedPatron.id,
						table_id: this.selectedPatron.table_id || null,
						order: {
							items: toRaw(selectedItems)
						}
					};

					let order = await this.orderService.prepare(data);

					// If event does NOT allow unpaid table orders, process payment now
					if (!this.event.allow_unpaid_table_orders) {
						try {
							let paymentData = await this.$paymentService.order(order);
							order.payment_type = paymentData.paymentType;
							order.payment_status = 'paid';
						} catch (e) {
							order.paid = false;
							order.status = 'declined';
							order.payment_status = 'voided';
						}
					}

					await this.orderService.create(order);

					// Reset the menu
					this.patronMenuItems.forEach(item => { item.amount = 0; });
					this.updatePatronOrderTotals();

					// Refresh patron data
					this.selectedPatron = await this.patronService.get(this.selectedPatron.id);
					this.patronOrders = (await this.orderService.index({ patron_id: this.selectedPatron.id })).items;
					await this.refreshPatrons();

				} catch (e) {
					this.patronOrderWarning = e.response ? e.response.data.error.message : e.message;
				} finally {
					this.patronOrderSaving = false;
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
