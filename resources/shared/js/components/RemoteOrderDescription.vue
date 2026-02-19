<template>

	<div class="remote-order-description">
		<h3>{{ $t('Order #{id}', { id: order.id }) }}</h3>
		<ul>
			<li v-for="product in order.order.items">{{product.amount}} x {{product.menuItem.name}}</li>
		</ul>

		<p>
			{{ $t('Table: {location}', { location: order.location }) }}<br />
			{{ $t('Client: {requester}', { requester: order.requester }) }}<br />
			{{ $t('Total: {total}', { total: paymentService.visualisePrice(order.totalPrice) }) }}
		</p>

		<p v-if="order.paid" class="alert alert-success">☑️ {{ $t('Paid') }}</p>
		<p v-if="!order.paid" class="alert alert-danger">✗ {{ $t('Not paid yet') }}</p>
	</div>

</template>

<script>
	export default {
		props: ['order', 'paymentService'],
	}
</script>