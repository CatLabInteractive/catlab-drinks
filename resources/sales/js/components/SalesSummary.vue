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
        <h2>
            Samenvatting
            <b-link class="btn btn-sm btn-info" :to="{ name: 'hq', params: { id: this.eventId } }">
                Bar HQ
            </b-link>
        </h2>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <div class="order-summary" v-if="summary">

            <table class="table">
                <tr>
                    <th>Item</th>
                    <th>Amount</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>

                <tr v-for="product in summary.items.items">
                    <td>{{product.menuItem.name}}</td>
                    <td>{{product.amount}}</td>
                    <td>€{{product.price.toFixed(2)}}</td>
                    <td>€{{product.totalSales.toFixed(2)}}</td>
                </tr>

                <tr>
                    <th>Total</th>
                    <td>{{summary.amount}}</td>
                    <td>&nbsp;</td>
                    <td>€{{summary.totalSales.toFixed(2)}}</td>
                </tr>
            </table>

        </div>

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
                summary: null
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

                this.summary = (await this.orderService.summary({

                }));
            }
        }
    }
</script>
