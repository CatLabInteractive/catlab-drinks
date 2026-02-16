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
                    <td>{{ $filters.formatDate(order.date) }}</td>
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
                    <td>Payment type</td>
                    <td>{{order.payment_type}}</td>
                </tr>

                <tr>
                    <td>Table</td>
                    <td>{{order.location}}</td>
                </tr>

                <tr>
                    <td>Payment</td>
                    <td>
                        <span v-if="order.paid"><i class="fas fa-check-square"></i> Paid
                            <span v-if="order.cardTransaction">by card</span>
                            <span v-if="!order.cardTransaction">in cash</span>
                        </span>
                        <span v-if="!order.paid"><i class="fas fa-times"></i> Not paid</span>
                    </td>
                </tr>

                <tr class="detail-view">
                    <td colspan="2">

                        <pre>{{receipt}}</pre>

                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</template>

<script>

    import {MenuService} from "../services/MenuService";
    import {OrderService} from "../services/OrderService";
    import {ReceiptPrinter} from "../utils/ReceiptPrinter";

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
        },

        computed: {
            receipt() {
                if (!this.order) {
                    return '';
                }

                const printer = new ReceiptPrinter(this.order);
                return printer.print();
            }
        }
    }
</script>
