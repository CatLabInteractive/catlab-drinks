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

    <b-container fluid>

        <h2>Bestellen</h2>

        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <b-alert variant="danger" :show="error !== null">
            {{error}}
        </b-alert>

        <div v-if="loaded && error === null">

            <b-alert variant="danger" :show="warning !== null">
                {{warning}}
            </b-alert>

            <b-row>
                <b-col>
                    <b-table striped hover :items="items" :fields="fields" v-if="loaded" class="order-table">

                        <template v-slot:cell(row-details)="row">
                            {{row.item.description}}
                        </template>

                        <template v-slot:cell(name)="row">
                            {{row.item.name}}<br />
                            <span class="price">€{{row.item.price.toFixed(2)}}</span>
                        </template>

                        <template v-slot:cell(amount)="row">

                            <span class="amount">{{row.item.amount}}</span>

                        </template>

                        <template v-slot:cell(actions)="row">

                            <span v-if="!row.item.isTotals">
                                <b-button variant="danger small" @click="down(row.item)" ><i class="fa fa-minus"></i></b-button>
                                <b-button variant="success small" @click="up(row.item)" ><i class="fa fa-plus"></i></b-button>
                            </span>

                        </template>

                    </b-table>
                </b-col>

            </b-row>

            <b-row>
                <b-col>
                    <b-form-group label="Tafelnummer">
                        <b-form-input type="text" v-model="tableNumber"></b-form-input>
                    </b-form-group>
                </b-col>
            </b-row>

            <b-row>
                <b-col>
                    <!--
                    <b-alert variant="danger" :show="warning !== null">
                        {{warning}}
                    </b-alert>
                    -->

                    <p><b-btn type="submit" variant="success" @click="submit()" size="lg">Bestellen</b-btn></p>
                </b-col>
            </b-row>

        </div>

        <!-- Modal Component -->
        <b-modal ref="warningModal" title="Woops" @ok="closeModal" button-size="lg" ok-only no-close-on-esc no-close-on-backdrop hide-header-close ok-variant="danger">
            <b-alert variant="danger" :show="warning !== null">
                {{warning}}
            </b-alert>
        </b-modal>

        <!-- Modal Component -->
        <b-modal ref="confirmModal" title="Bevestig bestelling" @ok="confirmOrder" button-size="lg" no-close-on-esc no-close-on-backdrop hide-header-close ok-variant="success" cancel-variant="danger">

            <div v-if="loadingOrder" class="text-center">
                <b-spinner />
            </div>

            <div v-if="!loadingOrder">
                <ul v-if="orderData">
                    <li v-for="item in orderData.order.items">
                        {{ item.amount}} x {{ item.name}}
                    </li>
                </ul>

                <p>Uw tafelnummer: {{ tableNumber }}</p>
            </div>
        </b-modal>

        <b-modal ref="successModal" title="We komen eraan!" @ok="closeModal" button-size="lg" ok-only ok-title="Nieuwe bestelling" ok-variant="success" no-close-on-esc no-close-on-backdrop>
            <b-alert variant="success" :show="true">
                We hebben je bestelling ontvangen (#{{orderId}}). Je bestelling staat in onze wachtlijst, we komen er zo snel mogelijk aan.
            </b-alert>
        </b-modal>

        <b-modal ref="processingOrderModal" title="Even wachten" no-close-on-esc no-close-on-backdrop hide-footer hide-header>
            <div class="text-center">
                <b-spinner />
            </div>
        </b-modal>

    </b-container>

</template>

<script>

    import {MenuService} from "../services/MenuService";

    export default {
        mounted() {

            this.service = new MenuService();

            this.refresh();

            // Look for name attribute
            if (this.$route.query.name) {
                this.userName = this.$route.query.name;
                localStorage.userName = this.userName;
            } else if(typeof(localStorage.userName) !== 'undefined') {
                this.userName = localStorage.userName;
            }

            // order token
            this.cardToken = null;
            if (this.$route.query.card) {
                this.cardToken = this.$route.query.card;
                localStorage.cardToken = this.cardToken;
            } else if (typeof(localStorage.cardToken) !== 'undefined') {
                this.cardToken = localStorage.cardToken;
            }

            // Look for name
            if (typeof(localStorage.tableNumber) !== 'undefined') {
                this.tableNumber = localStorage.tableNumber;
            }

        },

        data() {
            return {
                totals: {},
                loaded: false,
                saving: false,
                saved: false,
                toggling: null,
                orderId: null,
                orderData: null,
                items: [],
                fields: [
                    {
                        key: 'name',
                        label: 'Product',
                    },
                    {
                        key: 'amount',
                        label: 'Aantal',
                        class: 'text-center'
                    },

                    {
                        key: 'actions',
                        label: '',
                        class: 'text-right order-buttons'
                    }
                ],
                model: {},
                tableNumber: '',
                warning: null,
                error:  null,
                loadingOrder: false
            }
        },

        methods: {

            async refresh() {

                let items = [];
                try {
                    items = (await this.service.getMenu()).items;
                } catch (e) {
                    this.loaded = true;
                    this.error = e.response.data.error.message;
                    return;
                }

                items.forEach(
                    (item) => {
                        item.amount = 0;
                        item._showDetails = !!item.description;
                    }
                );

                this.totals = {
                    isTotals: true,
                    name: 'Totaal',
                    amount: 0,
                    price: 0.0,
                    _rowVariant: 'success'
                };

                this.items = items;
                this.items.push(this.totals);

                this.loaded = true;
                this.loadingOrder = false;

                this.recoverStoredOrder();
            },

            up(model) {
                model.amount ++;

                this.updateTotals();
                this.storeOrderForRecovery();
            },

            down(model) {
                model.amount --;

                if (model.amount < 0) {
                    model.amount = 0;
                }

                this.updateTotals();
                this.storeOrderForRecovery();
            },

            updateTotals() {
                let totalPrice = 0;
                let totalAmount = 0;

                this.items.forEach(
                    (item) => {
                        if (item.isTotals) {
                            return;
                        }

                        totalPrice += item.amount * item.price;
                        totalAmount += item.amount;
                    }
                );

                this.totals.price = totalPrice;
                this.totals.amount = totalAmount;
            },

            reset() {
                this.items.forEach(
                    (item) => {
                        if (item.isTotals) {
                            return;
                        }

                        item.amount = 0;
                    }
                );
                this.updateTotals();
                this.storeOrderForRecovery();
            },

            recoverStoredOrder() {

                if (typeof (localStorage.currentOrder) === 'undefined') {
                    return;
                }

                let amounts;
                try {
                    amounts = JSON.parse(localStorage.currentOrder);

                    this.items.forEach(
                        (item) => {
                            if (item.isTotals) {
                                return;
                            }

                            if (typeof (amounts[item.id]) !== 'undefined') {
                                item.amount = amounts[item.id];
                            }
                        }
                    );

                    this.updateTotals();

                } catch (e) {
                    console.error(e);
                    return;
                }

            },

            storeOrderForRecovery() {

                const amounts = {};
                this.items.forEach(
                    (item) => {
                        if (item.isTotals) {
                            return;
                        }

                        if (item.amount > 0) {
                            amounts[item.id] = item.amount;
                        }
                    }
                );

                localStorage.currentOrder = JSON.stringify(amounts);

            },

            async submit() {
                if (this.saving) {
                    return;
                }

                if (!this.tableNumber || this.tableNumber === '') {
                    this.warning = 'Gelieve een tafelnummer in te voeren.';
                    this.$refs.warningModal.show();
                    return;
                }

                if (this.totals.amount === 0) {
                    this.warning = 'Gelieve minstens 1 drankje te bestellen.';
                    this.$refs.warningModal.show();
                    return;
                }

                this.loadingOrder = false;

                const data = {};
                data.location = this.tableNumber;
                data.requester = this.userName;
                data.cardToken = this.cardToken;
                data.order = {
                    items: []
                };

                this.items.forEach(
                    (item) => {
                        if (item.isTotals) {
                            return;
                        }

                        if (item.amount > 0) {
                            data.order.items.push({
                                menuItem: {
                                    id: item.id
                                },
                                amount: item.amount,
                                name: item.name
                            });
                        }
                    }
                );

                this.orderData = data;
                this.$refs.confirmModal.show();
            },

            async confirmOrder() {

                this.$refs.processingOrderModal.show();
                try {
                    localStorage.tableNumber = this.tableNumber;

                    const order = await this.service.order(this.orderData);
                    this.$refs.processingOrderModal.hide();

                    this.loadingOrder = false;

                    this.orderId = order.id;

                    //this.$router.push({ name: 'ordersubmitted', params: { id: order.id  } });
                    this.$refs.successModal.show();
                    this.reset();

                } catch (e) {
                    this.$refs.processingOrderModal.hide();

                    this.warning = e.response.data.error.message;
                    this.$refs.warningModal.show();
                }

            },

            async closeModal() {
                this.$refs.warningModal.hide();
                this.$refs.successModal.hide();
            }

        }
    }
</script>
