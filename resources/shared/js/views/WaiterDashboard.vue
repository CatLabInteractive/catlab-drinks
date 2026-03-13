<template>
	<b-container fluid>
		<h1>
			{{ $t('Table Service') }}
			<b-badge v-if="event" variant="info">{{ event.name }}</b-badge>
		</h1>

		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<div v-if="loaded">
			<!-- Tabs: Tables / Order Queue -->
			<b-tabs v-model="activeTab">

				<!-- Tables Tab -->
				<b-tab :title="$t('Tables')">
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
								＋ {{ $t('New Patron') }}
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
								:to="{ name: 'patron', params: { id: eventId, patronId: patron.id } }"
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
										€{{ (patron.outstanding_balance).toFixed(2) }}
									</span>
								</div>
							</b-list-group-item>

							<b-list-group-item v-if="filteredPatrons.length === 0" class="text-muted text-center">
								{{ $t('No patrons at this table.') }}
							</b-list-group-item>
						</b-list-group>
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
	</b-container>
</template>

<script>
import {EventService} from "../../../shared/js/services/EventService";
import {TableService} from "../../../shared/js/services/TableService";
import {PatronService} from "../../../shared/js/services/PatronService";
import {OrderService} from "../../../shared/js/services/OrderService";

export default {
	async mounted() {
		this.eventId = this.$route.params.id;
		this.eventService = new EventService(window.ORGANISATION_ID);
		this.tableService = new TableService(this.eventId);
		this.patronService = new PatronService(this.eventId);
		this.orderService = new OrderService(this.eventId);

		await this.refresh();
	},

	data() {
		return {
			loaded: false,
			loadingPatrons: false,
			loadingOrders: false,
			activeTab: 0,
			eventId: null,
			event: null,
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
			]
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
		}
	},

	watch: {
		'$route'(to) {
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
			this.event = await this.eventService.get(this.eventId);
			this.tables = (await this.tableService.index({ sort: 'table_number' })).items;
			await this.refreshPatrons();
			this.loaded = true;
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
		},

		async createPatron() {
			const patron = {
				name: null,
				table_id: this.selectedTableId === 'none' ? null : this.selectedTableId
			};
			await this.patronService.create(patron);
			await this.refreshPatrons();
		},

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
