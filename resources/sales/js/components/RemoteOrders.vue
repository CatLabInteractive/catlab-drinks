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
        <h2>Bestellingen <remote-order-status v-bind:eventId="eventId"></remote-order-status></h2>
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
                Tafel: {{item.location}}<br />
                Besteller: {{item.requester}}<br />
                Totaal: €{{item.totalPrice.toFixed(2)}} (<strong>{{Math.ceil(item.totalPrice / 0.5)}} vakjes</strong>)
            </p>

            <p v-if="item.paid" class="alert alert-success"><i class="fas fa-check-square"></i> Betaald</p>
            <p v-if="!item.paid" class="alert alert-danger"><i class="fas fa-times"></i> Nog niet betaald</p>

            <p>
                <button class="btn btn-success" @click="acceptOrder(item)">Afgewerkt</button>
                <button class="btn btn-danger" @click="declineOrder(item)">Niet aanvaard</button>
            </p>

        </div>

        <p>
            <b-link :to="{ name: 'sales', params: { id: this.eventId } }" class="btn btn-sm btn-info">Order history</b-link>

            <b-link class="btn btn-sm btn-info" :to="{ name: 'summary', params: { id: this.eventId } }">
                Summary
            </b-link>
        </p>

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

            async acceptOrder(item) {

                item.status = 'processed';
                await this.orderService.update(item.id, item);
                this.refresh();

            },

            async declineOrder(item) {

                item.status = 'declined';
                await this.orderService.update(item.id, item);
                this.refresh();

            }
        }
    }
</script>
