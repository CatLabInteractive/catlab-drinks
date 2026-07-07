<!--
  - Shared order queue for the waiter views (POS TableService component
  - and the Manage WaiterDashboard). Owns its own fetching; call
  - refresh() (e.g. when its tab becomes visible).
  -->

<template>
	<div class="mt-3">
		<b-form-group>
			<b-form-checkbox v-model="filterMyOrders" inline>
				{{ $t('My orders only') }}
			</b-form-checkbox>
			<b-form-checkbox v-model="filterPreparedOnly" inline>
				{{ $t('Prepared only') }}
			</b-form-checkbox>
		</b-form-group>

		<div class="text-center" v-if="loading">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<b-table v-if="!loading" striped hover :items="filteredOrders" :fields="fields">
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
					v-if="allowMarkPrepared && row.item.status === ORDER_STATUS.PENDING"
					size="sm"
					variant="info"
					@click="markPrepared(row.item)"
					class="mr-1"
				>
					{{ $t('Prepared') }}
				</b-button>
				<b-button
					v-if="row.item.status !== ORDER_STATUS.DELIVERED && row.item.status !== ORDER_STATUS.DECLINED"
					size="sm"
					variant="success"
					@click="markDelivered(row.item)"
					class="mr-1"
				>
					{{ $t('Delivered') }}
				</b-button>
				<b-button
					v-if="row.item.status !== ORDER_STATUS.DECLINED"
					size="sm"
					variant="danger"
					@click="markVoided(row.item)"
				>
					{{ $t('Void') }}
				</b-button>
			</template>
		</b-table>
	</div>
</template>

<script>
	import {ORDER_STATUS, statusVariant, paymentStatusVariant} from '../orderStatus';

	export default {

		props: {
			orderService: { type: Object, required: true },
			allowMarkPrepared: { type: Boolean, default: true },
		},

		data() {
			return {
				ORDER_STATUS,
				loading: false,
				orders: [],
				filterMyOrders: false,
				filterPreparedOnly: false,
				fields: [
					{ key: 'id', label: '#' },
					{ key: 'requester', label: this.$t('Requester') },
					{ key: 'status', label: this.$t('Status') },
					{ key: 'payment_status', label: this.$t('Payment') },
					{ key: 'date', label: this.$t('Date') },
					{ key: 'actions', label: this.$t('Actions'), class: 'text-right' }
				]
			};
		},

		mounted() {
			this.refresh();
		},

		computed: {
			filteredOrders() {
				let orders = this.orders.filter(o =>
					o.status === ORDER_STATUS.PENDING || o.status === ORDER_STATUS.PREPARED
				);

				if (this.filterMyOrders && window.DEVICE_ID) {
					orders = orders.filter(o => o.assigned_device_id === window.DEVICE_ID);
				}

				if (this.filterPreparedOnly) {
					orders = orders.filter(o => o.status === ORDER_STATUS.PREPARED);
				}

				return orders;
			}
		},

		methods: {
			statusVariant,
			paymentStatusVariant,

			async refresh() {
				this.loading = true;
				this.orders = (await this.orderService.index({
					status: ORDER_STATUS.PENDING + ',' + ORDER_STATUS.PREPARED
				})).items;
				this.loading = false;
			},

			async markPrepared(order) {
				await this.orderService.markPrepared(order.id);
				await this.refresh();
			},

			async markDelivered(order) {
				await this.orderService.markDelivered(order.id);
				await this.refresh();
			},

			async markVoided(order) {
				if (confirm(this.$t('Are you sure you want to void this order?'))) {
					await this.orderService.markVoided(order.id);
					await this.refresh();
				}
			}
		}
	}
</script>
