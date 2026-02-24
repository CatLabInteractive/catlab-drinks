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
	<div v-if="event">
		<h2>
			{{ $t('Remote orders') }}
			<remote-order-status v-bind:eventId="event.id"></remote-order-status>

			<b-button v-if="this.event" size="sm" class="btn-light" :to="{ name: 'menu', params: { id: this.event.id } }">
				<span>✏️</span>
				<span class="sr-only">{{ $t('Menu items') }}</span>
			</b-button>
		</h2>

		<!-- Filter on category-->
		<b-form-group v-if="categories.length > 1">
			<select @change="changeFilterCategory($event, model)" v-model="categoryFilter" class="full-width form-control">
				<option v-for="category in categories" :value="category.value">
					{{ category.text }}
				</option>
			</select>
		</b-form-group>

		<!-- Filter assigned orders (POS only) -->
		<b-form-group v-if="currentDeviceId">
			<b-form-checkbox v-model="onlyAssignedOrders" @change="refresh()">
				{{ $t('Only show assigned orders') }}
			</b-form-checkbox>
		</b-form-group>

		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<b-alert v-if="loaded && items.length === 0" show>
			<relax></relax>
		</b-alert>

		<div class="remote-order-container">
			<div class="order" v-for="(item, index) in items">

				<remote-order-description :order="item" :paymentService="$paymentService"></remote-order-description>

				<p>
					<button class="btn btn-success" @click="acceptOrder(item)">{{ $t('Completed') }}</button>
					<button class="btn btn-danger" @click="declineOrder(item)">{{ $t('Not accepted') }}</button>
				</p>

			</div>
		</div>

		<!-- Modal Component -->
		<b-modal ref="processedModal" class="order-confirm-modal" ok-only button-size="lg" :title="$t('Order accepted')" ok-variant="success" no-close-on-backdrop :ok-title="$t('On my way!')">
			<p class="text-center"><span class="huge">👍</span></p>
			<div class="text-center alert alert-success">
				<span>{{ $t('Deliver order at table {location}.', { location: currentOrder.location }) }}</span>
			</div>

			<!-- Also repear order here -->
			<ul>
				<li v-for="product in currentOrder.order.items">{{product.amount}} x {{product.menuItem.name}}</li>
			</ul>

			<div class="text-center font-weight-bold" style="font-size: 5em;">
				TABLE {{currentOrder.location}}
			</div>

		</b-modal>

		<b-modal ref="processedDeclined" class="order-confirm-modal" ok-only button-size="lg" :title="$t('Order declined')" ok-variant="danger" no-close-on-backdrop :ok-title="$t('On my way!')">
			<p class="text-center"><span class="huge">👎</span></p>
			<div class="text-center alert alert-danger">
				<span v-if="currentOrder">{{ $t('Order declined. Notify table {location} and ask to enter order again.', { location: currentOrder.location }) }}</span>
			</div>

			<div class="text-center font-weight-bold" style="font-size: 5em;">
				TABLE {{currentOrder.location}}
			</div>
		</b-modal>

		<!-- Confirm order -->
		<b-modal ref="confirmAcceptFreeOrder" class="order-confirm-modal" :title="$t('Confirm unpaid order')" @ok="confirmAcceptUnpaidOrder" @cancel="clearCurrentOrder" button-size="lg" no-close-on-backdrop :ok-title="$t('Confirm order')" :cancel-title="$t('Cancel')" ok-variant="warning">

			<remote-order-description v-if="currentOrder" :order="currentOrder" :paymentService="$paymentService"></remote-order-description>

		</b-modal>

		<b-modal ref="confirmDecline" class="order-confirm-modal" :title="$t('Confirm declined order')" @ok="confirmDeclined" @cancel="clearCurrentOrder" button-size="lg" no-close-on-backdrop :ok-title="$t('Decline order')" :cancel-title="$t('Keep order')" ok-variant="danger">
			<div v-if="currentOrder">
				<div class="alert alert-danger">
					{{ $t('Are you sure you want to decline order #{id}?', { id: currentOrder.id }) }}<br />
					<span v-if="currentOrder.paid">{{ $t('The paid amount will be refunded.') }}<br /></span>
				</div>

				<div class="alert alert-danger">
					<strong>{{ $t('The client will not be notified, so go over to them and let them know why their order was declined.') }}</strong>
				</div>
			</div>
		</b-modal>

	</div>


</template>

<script>

	import {MenuService} from "../services/MenuService";
	import {OrderService} from "../services/OrderService";
	import {CategoryService} from "../services/CategoryService";

	import RemoteOrderDescription from './RemoteOrderDescription.vue';
	import RemoteOrderStatus from './RemoteOrderStatus.vue';
	import Relax from './Relax.vue';

	export default {

		components: {
			'remote-order-description': RemoteOrderDescription,
			'remote-order-status': RemoteOrderStatus,
			'relax': Relax,
		},

		props: [
			'event',
			'deviceId'
		],

		mounted() {
			if (this.event) {
				this.setEvent(this.event);
			}
		},

		beforeDestroy() {
			if (this.interval) {
				clearInterval(this.interval);
			}
		},

		data() {
			return {
				loaded: false,
				currentOrder: null,
				categoryFilter: '0',
				categories: [],
				items: [],
				onlyAssignedOrders: true,
				currentDeviceId: this.deviceId || null
			}
		},

		watch: {

			async event(newVal, oldVal) {
				if (newVal) {
					this.setEvent(newVal);
				}
			}

		},

		methods: {

			async setEvent(event) {

				this.menuService = new MenuService(event.id);
				this.orderService = new OrderService(event.id);
				this.categoryService = new CategoryService(event.id);

				if (this.interval) {
					clearInterval(this.interval);
				}

				this.loadCategories();

				this.refresh();
				this.interval = setInterval(
					() => {
						this.refresh();
					},
					5000
				);

			},

			async refresh() {

				this.loaded = true;

				const params = {
					sort: 'id',
					status: 'pending'
				};

				const items = (await this.orderService.index(params)).items.filter(
					(item) => {
						// Filter on assigned orders
						if (this.onlyAssignedOrders && this.currentDeviceId) {
							if (item.assigned_device_id !== this.currentDeviceId) {
								return false;
							}
						}

						// Filter on category
						if (!this.categoryFilter || this.categoryFilter == '0') {
							return true;
						}

						// If any of the items in the order is in the category, we show the order
						return item.order.items.filter(
							(orderItem) => {
								return orderItem.menuItem.category && orderItem.menuItem.category.id == this.categoryFilter;
							}
						).length > 0;
					}
				);

				// Calculate total price for each order
				items.forEach(
					(item) => {

						let totalPrice = 0;

						item.order.items.forEach(
							(orderItem) => {
								totalPrice += orderItem.amount * orderItem.price;
							});

						item.totalPrice = totalPrice;
					}
				);

				this.items = items;

			},

			showAcceptUnpaidOrder(order) {
				this.currentOrder = order;
				this.$refs.confirmAcceptFreeOrder.show();
			},

			visualisePrice(price) {
				return this.$paymentService.visualisePrice(price);
			},

			async declineOrder(order) {
				this.currentOrder = order;
				this.$refs.confirmDecline.show();
			},

			clearCurrentOrder() {
				// fing animation.
				setTimeout(
					() => {
						this.currentOrder = null;
					},
					500
				);
			},

			async loadCategories() {
				this.categories = [
					{
						value: '0',
						text: this.$t('Show all orders')
					}
				].concat(
					(await this.categoryService.index()).items.map(
						(category) => {
							return {
								value: category.id,
								text: this.$t('Only show "{name}" orders', { name: category.name })
							};
						}
					));
			},

			changeFilterCategory(event) {
				this.categoryFilter = event.target.value;
				this.refresh();

				// If this is a POS device, save the category filter to the server
				if (this.currentDeviceId) {
					this.saveCategoryFilter(this.categoryFilter);
				}
			},

			async saveCategoryFilter(categoryId) {
				try {
					await window.axios.put(
						CATLAB_DRINKS_CONFIG.API_PATH + '/devices/current/category-filter',
						{ category_filter_id: categoryId === '0' ? null : categoryId }
					);
				} catch (e) {
					console.error('Failed to save category filter:', e);
				}
			},

			// *****************************************************
			// Methods that actually do something.
			// *****************************************************
			/**
			 * @param order
			 * @returns {Promise<void>}
			 */
			 async acceptOrder(order) {

				this.currentOrder = order;

				// not paid? We need to get paid first!
				if (!order.paid) {
					try {
						let paymentData = await this.$paymentService.order(order, false);
					} catch (e) {
						this.declineOrder();
						return;
					}
				}

				// If payment server has not throw an exception, but order is still not paid, confirm that we are going to process it for free.
				if (!order.paid) {
					this.showAcceptUnpaidOrder(order);
					return;
				}

				order.status = 'processed';
				await this.orderService.update(order.id, order);

				this.$refs.processedModal.show();
				this.refresh();

			},

			async confirmDeclined() {

				this.currentOrder.status = 'declined';
				await this.orderService.update(this.currentOrder.id, this.currentOrder);

				this.$refs.processedDeclined.show();
				this.refresh();
			},

			async confirmAcceptUnpaidOrder() {

				this.currentOrder.status = 'processed';
				await this.orderService.update(this.currentOrder.id, this.currentOrder);

				this.$refs.processedModal.show();
				this.refresh();

			},
		}
	}
</script>
