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

        <h1>Events <b-button size="sm" class="btn-success" @click="createNew" title="Create new event">Create new</b-button></h1>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <b-row>
            <b-col>
                <b-table striped hover :items="items" :fields="fields" v-if="loaded">

                    <template v-slot:cell(name)="row">
                        <router-link :to="{ name: 'hq', params: { id: row.item.id } }" target="_blank">{{ row.item.name }}</router-link>
                    </template>

                    <template v-slot:cell(order_token)="row">
                        <!--
                        <a :href="row.item.order_url" target="_blank" title="Client panel">
                            <pre>{{ row.item.order_token }}></pre>
                        </a>
                        -->
                        <pre>{{ row.item.order_token }}</pre>
                    </template>

                    <template v-slot:cell(actions)="row">

                        <b-button size="sm" class="btn-info" :to="{ name: 'menu', params: { id: row.item.id } }" title="Edit menu">
                            <i class="fas fa-scroll"></i>
                            <span class="sr-only">Edit menu</span>
                        </b-button>

                        <b-link class="btn btn-sm btn-info" :to="{ name: 'hq', params: { id: row.item.id } }" title="Bar HQ">
                            <i class="fas fa-glass-martini"></i>
                            <span class="sr-only">Bar HQ</span>
                        </b-link>

                        <b-link class="btn btn-sm btn-info" :to="{ name: 'summary', params: { id: row.item.id } }" title="Sales overview">
                            <i class="fas fa-chart-line"></i>
                            <span class="sr-only">Sales overview</span>
                        </b-link>

                        <b-link class="btn btn-sm btn-success" :href="row.item.order_url" target="_blank" title="Client panel">
                            <i class="fas fa-user"></i>
                            <span class="sr-only">Client panel</span>
                        </b-link>

                        <b-link class="btn btn-sm btn-success" :to="{ name: 'attendees', params: { id: row.item.id } }" title="Attendees">
                            <i class="fas fa-users"></i>
                            <span class="sr-only">Attendees</span>
                        </b-link>

                        <b-link class="btn btn-sm btn-success" :to="{ name: 'checkIn', params: { id: row.item.id } }" title="Check-In">
                            <i class="fas fa-passport"></i>
                            <span class="sr-only">Check-In</span>
                        </b-link>

                        <b-button size="sm" class="" @click="edit(row.item, row.index)" title="Edit">
                            <i class="fas fa-edit"></i>
                            <span class="sr-only">Edit</span>
                        </b-button>

                        <b-button size="sm" @click="remove(row.item)" class="btn-danger" title="Remove">
                            <i class="fas fa-trash"></i>
                            <span class="sr-only">Delete</span>
                        </b-button>


                    </template>

                    <template v-slot:cell(is_selling)="row">

                        <b-button v-if="!row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)" class="btn-danger">
                            Closed
                        </b-button>

                        <b-button v-if="row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)" class="btn-success">
                            Open
                        </b-button>

                        <b-spinner v-if="toggling === row.item.id" small></b-spinner>
                    </template>

                </b-table>
            </b-col>

            <b-col lg="4" v-if="showForm">
                <b-card :title="(model.id ? 'Edit event ID#' + model.id : 'New event')">
                    <form @submit.prevent="save">
                        <b-form-group label="Name">
                            <b-form-input type="text" v-model="model.name"></b-form-input>
                        </b-form-group>

                        <b-form-checkbox v-model="model.payment_cards">
                            Allow payment with NFC topup cards
                        </b-form-checkbox>

                        <b-form-checkbox v-model="model.payment_cash">
                            Allow payment in cash
                        </b-form-checkbox>

                        <b-form-checkbox v-model="model.payment_vouchers">
                            Allow payment with vouchers
                        </b-form-checkbox>

                        <b-form-checkbox v-model="model.allow_unpaid_online_orders">
                            Allow unpaid online orders (without providing card alias)
                        </b-form-checkbox>

                        <b-form-group label="Voucher value">
                            <b-form-input type="number" v-model="model.payment_voucher_value" step="0.01"></b-form-input>
                        </b-form-group>

                        <b-form-group label="Checkin URL (callback)">
                            <b-form-input type="text" v-model="model.checkin_url"></b-form-input>
                        </b-form-group>

                        <div>
                            <b-btn type="submit" variant="success">Save</b-btn>
                            <b-btn type="button" variant="light" @click="resetForm()">Reset</b-btn>

                            <b-alert v-if="saving" variant="none" show>Saving</b-alert>
                            <b-alert v-if="saved" variant="none" show="2">Saved</b-alert>
                        </div>
                    </form>
                </b-card>
            </b-col>
        </b-row>

    </b-container>

</template>

<script>

    import {EventService} from "../services/EventService";

    export default {
        mounted() {

            this.service = new EventService(window.ORGANISATION_ID); // hacky hacky
            this.resetForm();
            this.refreshEvents();

        },

        data() {
            return {
                showForm: false,
                loaded: false,
                saving: false,
                saved: false,
                toggling: 0,
                items: [],
                fields: [
                    {
                        key: 'id',
                        label: '#'
                    },
                    {
                        key: 'name',
                        label: 'Event',
                    },
                    {
                        key: 'order_token',
                        label: 'Order token',
                    },
                    {
                        key: 'is_selling',
                        label: 'Remote orders',
                        class: 'text-center'
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        class: 'text-right'
                    }
                ],
                model: {}
            }
        },

        methods: {

            async refreshEvents() {

                this.items = (await this.service.index({ sort: '!id' })).items;
                this.loaded = true;

            },

            async save() {

                if (!this.model.payment_vouchers) {
                    this.model.payment_voucher_value = null;
                }

                this.saving = true;
                if (this.model.id) {
                    await this.service.update(this.model.id, this.model);
                } else {
                    await this.service.create(this.model);
                    this.resetForm();
                }

                this.saving = false;
                this.saved = true;

                this.refreshEvents();

                setTimeout(
                    () => {
                        this.saved = false;
                    },
                    2500
                );

            },

            async edit(model, index) {

                this.model = Object.assign({}, model);
                this.showForm = true;

            },

            async remove(model) {

                if (confirm('Are you sure you want to remove this event?')) {
                    if (this.model.id === model.id) {
                        this.model = {};
                    }

                    await this.service.delete(model.id);
                    await this.refreshEvents();
                }

            },

            async toggleIsSelling(model) {

                this.toggling = model.id;
                model.is_selling = !model.is_selling;
                await this.service.update(model.id, model);
                this.toggling = null;

            },

            createNew() {
                this.showForm = true;
                this.resetForm();
            },

            resetForm() {
                this.model = {
                    payment_cards: true,
                    allow_unpaid_online_orders: false
                };
            }
        }
    }
</script>
