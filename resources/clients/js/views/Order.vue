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

        <h1>CatLab Drinks</h1>
        <h2>{{ $t('Order') }}</h2>

        <div class="text-center" v-if="!loaded">
            <b-spinner :label="$t('Loading data')" />
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

                        <template v-slot:row-details="row">
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
                                <b-button variant="danger small" @click="down(row.item)" ><span>−</span></b-button>
                                <b-button variant="success small" @click="up(row.item)" ><span>＋</span></b-button>
                            </span>

                        </template>

                    </b-table>
                </b-col>

            </b-row>

            <b-row>
                <b-col>
                    <b-form-group :label="$t('Table number')">
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

                    <p><b-btn type="submit" variant="success" @click="submit()" size="lg">{{ $t('Place order') }}</b-btn></p>
                </b-col>
            </b-row>

        </div>

        <!-- Modal Component -->
        <b-modal ref="warningModal" :title="$t('Oops')" @ok="closeModal" button-size="lg" ok-only no-close-on-esc no-close-on-backdrop hide-header-close ok-variant="danger">
            <b-alert variant="danger" :show="warning !== null">
                {{warning}}
            </b-alert>
        </b-modal>

        <!-- Modal Component -->
        <b-modal ref="confirmModal" :title="$t('Confirm order')" @ok="confirmOrder" button-size="lg" no-close-on-esc no-close-on-backdrop hide-header-close ok-variant="success" cancel-variant="danger">

            <div v-if="loadingOrder" class="text-center">
                <b-spinner />
            </div>

            <div v-if="!loadingOrder">
                <ul v-if="orderData">
                    <li v-for="item in orderData.order.items">
                        {{ item.amount}} x {{ item.name}}
                    </li>
                </ul>

                <p>{{ $t('Your table number: {tableNumber}', { tableNumber: tableNumber }) }}</p>
            </div>
        </b-modal>

        <b-modal ref="successModal" :title="$t('We\'re on our way!')" @ok="closeModal" button-size="lg" ok-only :ok-title="$t('New order')" ok-variant="success" no-close-on-esc no-close-on-backdrop>
            <b-alert variant="success" :show="true">
                {{ $t('We have received your order ({orderIds}). Your order is in our queue, we will be there as soon as possible.', { orderIds: orderIds }) }}
            </b-alert>
        </b-modal>

        <b-modal ref="processingOrderModal" :title="$t('Please wait')" no-close-on-esc no-close-on-backdrop hide-footer hide-header>
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

            // Look for name attribute
            if (this.$route.query.name) {
                this.userName = this.$route.query.name;
                this.setLocalStorage('userName', this.userName);
            } else if(this.getLocalStorage('userName')) {
                this.userName = this.getLocalStorage('userName');
            }

            // order token
            this.cardToken = null;
            if (this.$route.query.card) {
                this.cardToken = this.$route.query.card;
                //this.setLocalStorage('cardToken', this.cardToken);
            } else if (this.getLocalStorage('cardToken')) {
                //this.cardToken = this.getLocalStorage('cardToken');
            }

            // Look for name
            if (this.getLocalStorage('tableNumber')) {
                this.tableNumber = this.getLocalStorage('tableNumber');
            }

            this.refresh();

        },

        data() {
            return {
                totals: {},
                loaded: false,
                saving: false,
                saved: false,
                toggling: null,
                orderIds: null,
                orderData: null,
                items: [],
                model: {},
                tableNumber: '',
                warning: null,
                error:  null,
                loadingOrder: false
            }
        },

        computed: {
            fields() {
                return [
                    {
                        key: 'name',
                        label: this.$t('Product'),
                    },
                    {
                        key: 'amount',
                        label: this.$t('Amount'),
                        class: 'text-center'
                    },
                    {
                        key: 'actions',
                        label: '',
                        class: 'text-right order-buttons'
                    }
                ];
            }
        },

        methods: {

            async refresh() {

                let items = [];
                try {
                    items = (await this.service.getMenu(this.cardToken)).items;
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
                    name: this.$t('Total'),
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

                let currentOrder = this.getLocalStorage('currentOrder');
                if (!currentOrder) {
                    return;
                }

                let amounts;
                try {
                    if (typeof(currentOrder) !== 'object') {
                        amounts = JSON.parse(currentOrder);
                    } else {
                        amounts = currentOrder;
                    }

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

                this.setLocalStorage('currentOrder', JSON.stringify(amounts));
            },

            async submit() {
                if (this.saving) {
                    return;
                }

                // scroll to top
                window.scrollTo(0, 0);

                if (!this.tableNumber || this.tableNumber === '') {
                    this.warning = this.$t('Please enter a table number.');
                    this.$refs.warningModal.show();
                    return;
                }

                if (this.totals.amount === 0) {
                    this.warning = this.$t('Please order at least 1 item.');
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
                    this.setLocalStorage('tableNumber', this.tableNumber);

                    const orders = await this.service.order(this.orderData, this.cardToken);
                    this.$refs.processingOrderModal.hide();

                    this.loadingOrder = false;

                    this.orderIds = orders.items.map((order) => '#' + order.id).join(', ');

                    //this.$router.push({ name: 'ordersubmitted', params: { id: order.id  } });
                    this.$refs.successModal.show();
                    this.reset();

                } catch (e) {
                    this.$refs.processingOrderModal.hide();

                    this.warning = this.$t('Network connection error. Please check network connection.');
                    if (
                        e.response &&
                        e.response.data &&
                        e.response.data.error &&
                        e.response.data.error.message
                    ) {
                        this.warning = e.response.data.error.message;
                    }
                    this.$refs.warningModal.show();
                }

            },

            async closeModal() {
                this.$refs.warningModal.hide();
                this.$refs.successModal.hide();
            },

            /**
             * (try to) set in localstorage.
             */
            setLocalStorage(name, value) {
                try {
                    localStorage[name] = value;
                } catch (e) {
                    console.log(e);

                    // try to set cookie
                    try {
                        this.$cookies.set(name, value);
                    } catch (e) {
                        console.log(e);
                        // do nothing.
                    }
                }
            },

            /**
             * (try to) load from localStorage.
             * @param name
             * @returns {null|any}
             */
            getLocalStorage(name) {
                try {
                    if (localStorage[name]) {
                        return localStorage[name];
                    }

                    if (this.$cookies.get(name)) {
                        return this.$cookies.get(name);
                    }

                    return null;
                } catch (e) {
                    console.log(e);

                    // check cookie
                    try {
                        if (this.$cookies.get(name)) {
                            return this.$cookies.get(name);
                        }
                    } catch (e) {
                        console.log(e);
                    }
                }
            }
        }
    }
</script>
