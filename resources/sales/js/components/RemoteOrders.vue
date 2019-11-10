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
        <h2>Remote orders <remote-order-status v-bind:eventId="eventId"></remote-order-status></h2>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <b-alert v-if="loaded && items.length === 0" show>
            <relax></relax>
        </b-alert>

        <div class="order" v-for="(item, index) in items">

            <h3>Order #{{item.id}}</h3>
            <ul>
                <li v-for="product in item.order.items">{{product.amount}} x {{product.menuItem.name}}</li>
            </ul>

            <p>
                Table: {{item.location}}<br />
                Client: {{item.requester}}<br />
                Total: €{{item.totalPrice.toFixed(2)}} (<strong>{{Math.ceil(item.totalPrice / 0.5)}} vakjes</strong>)
            </p>

            <p v-if="item.paid" class="alert alert-success"><i class="fas fa-check-square"></i> Paid</p>
            <p v-if="!item.paid" class="alert alert-danger"><i class="fas fa-times"></i> Not paid yet</p>

            <p>
                <button class="btn btn-success" @click="acceptOrder(item)">Completed</button>
                <button class="btn btn-danger" @click="declineOrder(item)">Not accepted</button>
            </p>

        </div>

        <p v-if="this.eventId">
            <b-link :to="{ name: 'sales', params: { id: this.eventId } }" class="btn btn-sm btn-info">Order history</b-link>

            <b-link class="btn btn-sm btn-info" :to="{ name: 'summary', params: { id: this.eventId } }">
                Summary
            </b-link>
        </p>

        <!-- Modal Component -->
        <b-modal ref="processedModal" class="order-confirm-modal" ok-only button-size="lg" title="Order accepted" ok-variant="success" no-close-on-backdrop>
            <p class="text-center"><i class="fas fa-thumbs-up huge"></i></p>
            <div class="text-center alert alert-success">
                <span v-if="currentOrder">Payment successful, deliver order at <strong>table {{currentOrder.location}}</strong>.</span>
            </div>
        </b-modal>

        <b-modal ref="confirmDecline" class="order-confirm-modal" title="Confirm declined order" @ok="confirmDeclined" @cancel="cancelDecline" button-size="lg" no-close-on-backdrop ok-title="Decline order" cancel-title="Cancel" ok-variant="danger">
            <div class="alert alert-danger">
                Are you sure you want to decline order <span v-if="currentOrder">#{{currentOrder.id}}</span>?<br />
                <span v-if="currentOrder && currentOrder.paid">The paid amount will be refunded.<br /></span>
            </div>

            <div class="alert alert-danger">
                <strong>The client will not be notified, so go over to them and let them know why their order was declined.</strong>
            </div>
        </b-modal>

    </div>


</template>

<script>

    import {MenuService} from "../services/MenuService";
    import {OrderService} from "../services/OrderService";

    export default {

        props: [
            'eventId'
        ],

        mounted() {


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
                items: []
            }
        },

        watch: {

            eventId(newVal, oldVal) {

                this.menuService = new MenuService(newVal);
                this.orderService = new OrderService(newVal);

                if (this.interval) {
                    clearInterval(this.interval);
                }

                this.refresh();
                this.interval = setInterval(
                    () => {
                        this.refresh();
                    },
                    5000
                );
            }

        },

        methods: {

            async refresh() {

                this.loaded = true;

                const items = (await this.orderService.index({
                    sort: 'id',
                    status: 'pending'
                })).items;
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

                order.status = 'processed';
                await this.orderService.update(order.id, order);

                this.$refs.processedModal.show();
                setTimeout(function () {
                    this.$refs.processedModal.hide();
                }.bind(this), 2000);

                this.refresh();
            },

            async declineOrder(order) {
                this.currentOrder = order;
                this.$refs.confirmDecline.show();
            },

            async cancelDecline() {
                this.currentOrder = null;
            },

            async confirmDeclined() {

                this.$refs.confirmDecline.hide();

                this.currentOrder.status = 'declined';
                await this.orderService.update(this.currentOrder.id, this.currentOrder);

                this.currentOrder = null;
                this.refresh();
            }
        }
    }
</script>
