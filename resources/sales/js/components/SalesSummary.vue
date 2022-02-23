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
            <span v-if="event">{{event.name}} - </span>Sales summary
            <b-link class="btn btn-sm btn-info d-print-none" :to="{ name: 'hq', params: { id: this.eventId } }">
                Bar HQ
            </b-link>
        </h2>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <div class="order-summary" v-if="summary">

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Amount</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                    <template v-for="productGroup in groupedItems">
                        <tr v-for="(product, index) in productGroup.sales">
                            <td><span v-if="index === 0">{{product.menuItem.name}}</span></td>
                            <td>{{product.amount}}</td>
                            <td>€{{product.price.toFixed(2)}}</td>
                            <td>€{{product.totalSales.toFixed(2)}}</td>
                        </tr>
                    </template>
                </tbody>

                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th>{{summary.amount}}</th>
                        <th>&nbsp;</th>
                        <th>€{{summary.totalSales.toFixed(2)}}</th>
                    </tr>
                </tfoot>
            </table>

        </div>

    </div>


</template>

<script>

    import {MenuService} from "../services/MenuService";
    import {OrderService} from "../services/OrderService";
    import {EventService} from "../services/EventService";

    export default {

        props: [
            'eventId'
        ],

        mounted() {

            this.eventService = new EventService(window.ORGANISATION_ID); // hacky hacky

        },

        beforeDestroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        },

        destroyed() {

            if (this.orderService) {
                this.orderService.destroy();
            }

            if (this.menuService) {
                this.menuService.destroy();
            }

            if (this.eventService) {
                this.eventService.destroy();
            }

        },

        data() {
            return {
                loaded: false,
                summary: null,
                groupedItems: [],
                event: null
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
                        //this.refresh();
                    },
                    30000
                );
            }

        },

        methods: {

            async refresh() {

                this.event = await this.eventService.get(this.eventId);

                this.loaded = true;

                this.summary = (await this.orderService.summary({

                }));

                this.groupedItems = [];

                let indexMap = {};
                this.summary.items.items.forEach(
                    (summaryLine) => {

                        const key = summaryLine.menuItem.id;

                        if (typeof(indexMap[key]) === 'undefined') {
                            indexMap[key] = this.groupedItems.push({
                                menuItem: summaryLine.menuItem,
                                sales: []
                            }) - 1;
                        }

                        this.groupedItems[indexMap[key]].sales.push(summaryLine);
                    }
                );

                console.log(this.groupedItems);
            }
        }
    }
</script>
