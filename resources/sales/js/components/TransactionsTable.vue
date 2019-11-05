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
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <div class="alert alert-warning" v-if="loaded && transactions.length === 0">We have not recorded any transactions yet.</div>

        <b-table striped hover :items="transactions" :fields="fields" v-if="transactions.length > 0">

            <template v-slot:cell(order)="row">
                <span v-if="row.item.order">
                    <a href="javascript:void(0)" class="btn btn-sm btn-info" v-on:click="showOrder(row.item.order)">Order #{{row.item.order.id}}</a>
                </span>
                <span v-else>{{ row.item.type }}</span>
            </template>

            <template v-slot:cell(amount)="row">
                {{row.item.getVisibleAmount()}}
            </template>

            <template v-slot:cell(date)="row">
                {{row.item.date | formatDate}}
            </template>

        </b-table>

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
                loaded: false,
                loading: false,
                fields: []
            }
        },

        methods: {

            async refresh() {

                if (!this.card) {
                    this.fields = [
                        'id',
                        'cardUid'
                    ];
                } else {
                    this.fields = [];
                }

                this.fields.push('transactionId', 'order', 'amount', 'date');

                this.loading = true;

                this.transactions = await this.$cardService.getTransactions(this.card);
                this.loaded = true;
                this.loading = false;

            },

            showOrder(order) {
                console.log(order);

                this.orderDetails = order;
                this.$refs.orderModal.show();
            }
        }
    }
</script>
