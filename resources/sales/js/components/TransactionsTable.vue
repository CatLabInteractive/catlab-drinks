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
        <table class="table">

            <tr v-for="transaction in this.transactions">

                <td v-if="!card">{{ transaction.id }}</td>
                <td v-if="!card">{{ transaction.cardUid }}</td>
                <td>{{ transaction.transactionId }}</td>
                <td>
                    <span v-if="transaction.order">
                        <a href="javascript:void(0)" class="btn btn-sm btn-info" v-on:click="showOrder(transaction.order)">Order #{{transaction.order.id}}</a>
                    </span>
                    <span v-else>{{ transaction.type }}</span>
                </td>
                <td>{{ transaction.getVisibleAmount() }}</td>
                <td>{{ transaction.date ? transaction.date : null | formatDate }}</td>

            </tr>

        </table>

        <!-- Modal Component -->
        <b-modal ref="orderModal" title="Order details" ok-only>
            <div v-if="orderDetails">
                <order-details :order="orderDetails"></order-details>
            </div>
        </b-modal>

    </div>

</template>

<script>

    import {Card} from "../nfccards/models/Card";

    export default {
        mounted() {

            this.refresh();

        },

        props: {
            'card': Card
        },

        data() {
            return {
                transactions: [],
                orderDetails: null,
            }
        },

        methods: {

            async refresh() {

                this.transactions = await this.$cardService.getTransactions(this.card);

            },

            showOrder(order) {
                console.log(order);

                this.orderDetails = order;
                this.$refs.orderModal.show();
            }
        }
    }
</script>
