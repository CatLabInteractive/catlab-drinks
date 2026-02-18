<template>

	<div class="remote-order-description">
		<h3>Order #{{order.id}}</h3>
		<ul>
			<li v-for="product in order.order.items">{{product.amount}} x {{product.menuItem.name}}</li>
		</ul>

		<p>
			Table: {{order.location}}<br />
			Client: {{order.requester}}<br />
			Total: {{ paymentService.visualisePrice(order.totalPrice) }}
		</p>

		<p v-if="order.paid" class="alert alert-success">☑️ Paid</p>
		<p v-if="!order.paid" class="alert alert-danger">✗ Not paid yet</p>
	</div>

</template>

<script>
	export default {
		props: ['order', 'paymentService'],
	}
</script>