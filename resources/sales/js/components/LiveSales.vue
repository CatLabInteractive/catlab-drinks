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
            Bar

            <b-button v-if="this.eventId" size="sm" class="btn-light" :to="{ name: 'menu', params: { id: this.eventId } }">
                <i class="fas fa-edit"></i>
                <span class="sr-only">Menu items</span>
            </b-button>
        </h2>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <b-row v-if="loaded">
            <b-col cols="12">

                <div class="live-orders">
                    <div v-for="(item, index) in items" class="product" v-on:click="increaseOrder(item, index, $event)">
                        <span class="name">{{ item.name}}</span>
                        <span class="buttons">

                            <button class="btn btn-danger btn-sm" v-on:click="decreaseOrder(item, index, $event)">
                                <i class="fa fa-minus fa-sm"></i>
                                <span class="sr-only">-</span>
                            </button>

                            <!--
                            <button class="btn btn-success btn-sm" v-on:click="increaseOrder(item, index, $event)">
                                <i class="fa fa-plus fa-sm"></i>
                                <span class="sr-only">+</span>
                            </button>
                            -->

                        </span>
                        <span class="amount">{{item.amount}}</span>
                    </div>
                </div>

            </b-col>
        </b-row>

        <b-row>
            <b-col cols="12">
                <div class="total">
                    <p>Totaal: {{totals.amount}} stuks = €{{totals.price.toFixed(2)}} (<strong>{{Math.ceil(totals.price / 0.5)}} vakjes</strong>)</p>
                </div>

                <p>
                    <button class="btn btn-success btn-lg" v-on:click="submit">Bevestigen</button>

                    <b-alert v-if="saving" variant="none" show>Saving</b-alert>
                    <b-alert v-if="saved" variant="none" :show="2">{{ savedMessage }}</b-alert>
                </p>

                <b-alert variant="danger" :show="warning !== null">
                    {{warning}}
                </b-alert>

            </b-col>
        </b-row>


        <!-- Modal Component -->
        <b-modal ref="confirmModal" class="order-confirm-modal" title="Bestelling bevestigen" @ok="confirm" @cancel="cancel" button-size="lg">
            <ul>
                <li v-for="(item, index) in selectedItems">
                    {{item.amount}} x {{item.menuItem.name}}
                </li>
            </ul>

            <p class="total">Totaal: {{totals.amount}} stuks = €{{totals.price.toFixed(2)}} <strong>({{Math.ceil(totals.price / 0.5)}} vakjes)</strong></p>
        </b-modal>
    </div>

</template>

<script>

    import {MenuService} from "../services/MenuService";
    import {OrderService} from "../services/OrderService";
    import {CardService} from "../nfccards/CardService";
    import {EventService} from "../services/EventService";

    export default {

        props: [
            'eventId'
        ],

        mounted() {


        },

        data() {
            return {
                loaded: false,
                items: [],
                selectedItems: [],
                saved: false,
                saving: false,
                savedMessage: '',
                warning: null,
                totals: {
                    items: 0,
                    price: 0
                }
            }
        },

        watch: {

            eventId(newVal, oldVal) {

                this.menuService = new MenuService(newVal);
                this.orderService = new OrderService(newVal);
                this.eventService = new EventService(newVal);

                this.eventService.get(newVal, { expand: 'organisation', fields: '*,organisation.*,organisation.secret' })
                    .then(
                        (event) => {
                            this.event = event;

                            this.$cardService
                                .setPassword(event.organisation.secret);
                        }
                    );

                this.refresh();

            }

        },

        methods: {

            async refresh() {

                this.items = (await this.menuService.index()).items;
                this.reset();

                this.loaded = true;

            },

            reset() {
                this.items.forEach(
                    (item) => {
                        item.amount = 0;
                    }
                );
                this.updateTotals();
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

            async increaseOrder(product, index, event) {

                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                product.amount ++;

                this.items.splice(index, 1, product);
                this.updateTotals();
            },

            async decreaseOrder(product, index, event) {

                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                product.amount --;
                if (product.amount < 0) {
                    product.amount = 0;
                }

                this.items.splice(index, 1, product);
                this.updateTotals();
            },

            async submit() {

                const selectedItems = [];

                let totalAmount = 0;
                this.items.forEach(
                    (item) => {
                        if (item.amount > 0) {
                            selectedItems.push({
                                menuItem: {
                                    id: item.id,
                                    name: item.name
                                },
                                amount: item.amount
                            });
                            totalAmount += item.amount;
                        }
                    }
                );

                if (totalAmount === 0) {
                    return;
                }

                this.selectedItems = selectedItems;
                this.$refs.confirmModal.show();
            },

            async cancel() {
                this.$refs.confirmModal.hide();
            },

            async confirm() {

                this.saving = true;
                this.warning = null;

                this.$refs.confirmModal.hide();

                try {
                    const data = {
                        location: 'Manual',
                        status: 'processed',
                        paid: false,
                        price: this.totals.price,
                        order: {
                            items: this.selectedItems
                        }
                    };

                    let order = await this.orderService.prepare(data);

                    try {
                        await this.$paymentService.order(order);

                        order.paid = true;
                        order.status = 'processed';

                    } catch (e) {
                        order.paid = false;
                        order.status = 'declined';
                    }

                    order = await this.orderService.create(order);
                    this.reset();

                    this.saving = false;
                    this.saved = true;
                    this.savedMessage = 'Order ' + order.id + ' saved';

                    setTimeout(
                        () => {
                            this.saved = false;
                        },
                        2000
                    );

                } catch (e) {
                    this.saving = false;
                    this.warning = e.response.data.error.message;
                }
            }
        }
    }
</script>