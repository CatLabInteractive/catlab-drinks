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
        <table class="table table-striped">

            <tbody>
                <tr>
                    <td>ID</td>
                    <td>{{ order.id}}</td>
                </tr>

                <tr>
                    <td>Status</td>
                    <td>{{ order.status}}</td>
                </tr>

                <tr>
                    <td>Date</td>
                    <td>{{order.date | formatDate}}</td>
                </tr>

                <tr>
                    <td>Table</td>
                    <td>{{order.location}}</td>
                </tr>

                <tr v-if="order.requester">
                    <td>Client</td>
                    <td>{{order.requester}}</td>
                </tr>

                <tr v-if="order.discount > 0">
                    <td>Discount</td>
                    <td>{{ order.discount }}%</td>
                </tr>

                <tr>
                    <td>Total</td>
                    <td>{{totalPrice.toFixed(2)}}</td>
                </tr>

                <tr>
                    <td>Payment</td>
                    <td>
                        <span v-if="order.paid"><i class="fas fa-check-square"></i> Paid by card</span>
                        <span v-if="!order.paid"><i class="fas fa-times"></i> Cash transaction</span>
                    </td>
                </tr>

                <tr class="detail-view">
                    <td colspan="2">

                        <ul>
                            <li v-for="product in order.order.items">
                                {{product.amount}} x
                                {{product.menuItem.name}}
                            </li>
                        </ul>

                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</template>

<script>

    import {MenuService} from "../services/MenuService";
    import {OrderService} from "../services/OrderService";

    export default {

        props: [
            'order'
        ],

        mounted() {
            this.recalculateTotals();
        },

        watch: {
            eventId(newVal, oldVal) {
                //this.order = newVal;
                this.recalculateTotals();
            }
        },

        data() {
            return {
                totalPrice: 0
            }
        },

        methods: {
            recalculateTotals() {
                // calculate the prices
                let totalPrice = 0;

                this.order.order.items.forEach(
                    (orderItem) => {
                        totalPrice += orderItem.amount * orderItem.price;
                    });

                this.totalPrice = totalPrice;
            }
        }
    }
</script>
