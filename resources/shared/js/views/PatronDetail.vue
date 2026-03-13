<template>
	<b-container fluid>
		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<div v-if="loaded && patron">
			<h2>
				{{ patron.name || ($t('Patron') + ' #' + patron.id) }}
				<b-badge v-if="patron.has_unpaid_orders" variant="warning">{{ $t('Unpaid') }}</b-badge>
			</h2>

			<b-card class="mb-3" v-if="patron.outstanding_balance > 0">
				<b-row>
					<b-col>
						<h5>{{ $t('Outstanding Balance') }}</h5>
						<span class="h3 text-danger">€{{ patron.outstanding_balance.toFixed(2) }}</span>
					</b-col>
					<b-col class="text-right" cols="auto">
						<b-button variant="success" size="lg" @click="settleBalance">
							{{ $t('Pay Outstanding Balance') }}
						</b-button>
					</b-col>
				</b-row>
			</b-card>

			<h4>{{ $t('Orders') }}</h4>
			<b-table striped hover :items="orders" :fields="orderFields" v-if="orders.length > 0">
				<template v-slot:cell(items)="row">
					<span v-for="(item, index) in (row.item.order ? row.item.order.items : [])" :key="index">
						{{ item.amount }}× {{ item.name }}<span v-if="index < row.item.order.items.length - 1">, </span>
					</span>
				</template>

				<template v-slot:cell(status)="row">
					<b-badge :variant="statusVariant(row.item.status)">{{ row.item.status }}</b-badge>
				</template>

				<template v-slot:cell(payment_status)="row">
					<b-badge :variant="paymentStatusVariant(row.item.payment_status)">{{ row.item.payment_status }}</b-badge>
				</template>

				<template v-slot:cell(price)="row">
					€{{ row.item.price ? row.item.price.toFixed(2) : '0.00' }}
				</template>
			</b-table>

			<b-alert v-if="orders.length === 0" variant="info" show>
				{{ $t('No orders for this patron.') }}
			</b-alert>

			<b-button variant="outline-secondary" @click="$router.back()">
				← {{ $t('Back') }}
			</b-button>
		</div>
	</b-container>
</template>

<script>
import {PatronService} from "../../../shared/js/services/PatronService";
import {OrderService} from "../../../shared/js/services/OrderService";

export default {
	async mounted() {
		this.eventId = this.$route.params.id;
		this.patronId = this.$route.params.patronId;
		this.patronService = new PatronService(this.eventId);
		this.orderService = new OrderService(this.eventId);
		await this.refresh();
	},

	data() {
		return {
			loaded: false,
			patron: null,
			orders: [],
			orderFields: [
				{ key: 'id', label: '#' },
				{ key: 'items', label: this.$t('Items') },
				{ key: 'status', label: this.$t('Status') },
				{ key: 'payment_status', label: this.$t('Payment') },
				{ key: 'price', label: this.$t('Price') },
				{ key: 'date', label: this.$t('Date') }
			]
		}
	},

	watch: {
		'$route'(to) {
			this.eventId = to.params.id;
			this.patronId = to.params.patronId;
			this.refresh();
		}
	},

	methods: {
		async refresh() {
			this.patron = await this.patronService.get(this.patronId);
			this.orders = (await this.orderService.index({ patron_id: this.patronId })).items;
			this.loaded = true;
		},

		async settleBalance() {
			if (confirm(this.$t('Pay all outstanding orders for this patron?'))) {
				// Mark all unpaid orders as paid
				for (const order of this.orders) {
					if (order.payment_status === 'unpaid') {
						await this.orderService.update(order.id, { payment_status: 'paid' });
					}
				}
				await this.refresh();
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
