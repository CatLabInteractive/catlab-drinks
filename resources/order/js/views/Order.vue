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
        <p>
            Wat wilt u bestellen?
        </p>

        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <div v-if="loaded">

            <b-row>
                <b-col>
                    <b-form-group label="Tafelnummer">
                        <b-form-input type="text" v-model="tableNumber"></b-form-input>
                    </b-form-group>
                </b-col>
            </b-row>

            <b-row>
                <b-col>
                    <b-table striped hover :items="items" :fields="fields" v-if="loaded">

                        <template slot="price" slot-scope="row">
                            €{{row.item.price.toFixed(2)}}
                        </template>

                        <template slot="amount" slot-scope="row">

                            {{row.item.amount}}

                        </template>

                        <template slot="actions" slot-scope="row">

                            <span v-if="!row.item.isTotals">
                                <b-button variant="success small" @click="up(row.item)" size="sm"><i class="fas fa-plus"></i></b-button>
                                <b-button variant="danger small" @click="down(row.item)" size="sm"><i class="fas fa-minus"></i></b-button>
                            </span>

                        </template>

                    </b-table>
                </b-col>

            </b-row>

            <b-row>
                <b-col>
                    <b-btn type="submit" variant="success" @click="submit()">Bestellen</b-btn>
                </b-col>
            </b-row>

        </div>

    </b-container>

</template>

<script>

    import {MenuService} from "../services/MenuService";

    export default {
        mounted() {

            this.service = new MenuService();

            this.refresh();

        },

        data() {
            return {
                totals: {},
                loaded: false,
                saving: false,
                saved: false,
                toggling: null,
                items: [],
                fields: [
                    {
                        key: 'name',
                        label: 'Product',
                    },
                    {
                        key: 'price',
                        label: 'Prijs',
                        class: 'text-center'
                    },
                    {
                        key: 'amount',
                        label: 'Aantal',
                        class: 'text-center'
                    },

                    {
                        key: 'actions',
                        class: 'text-right'
                    }
                ],
                model: {},
                tableNumber: ''
            }
        },

        methods: {

            async refresh() {
                const items = (await this.service.getMenu()).items;
                items.forEach(
                    (item) => {
                        item.amount = 0;
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
            },

            up(model) {
                model.amount ++;

                this.updateTotals();
            },

            down(model) {
                model.amount --;

                if (model.amount < 0) {
                    model.amount = 0;
                }

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

            async submit() {
                if (this.saving) {
                    return;
                }

                if (!this.tableNumber || this.tableNumber === '') {
                    alert('Gelieve een tafelnummer in te voeren.');
                    return;
                }

                const data = {};
                data.tableNumber = this.tableNumber;
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
                                amount: item.amount
                            });
                        }
                    }
                );


                await this.service.order(data);
            }

        }
    }
</script>