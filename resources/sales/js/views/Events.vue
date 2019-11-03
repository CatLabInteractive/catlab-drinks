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

        <h1>Events</h1>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <b-row>
            <b-col>
                <b-table striped hover :items="items" :fields="fields" v-if="loaded">

                    <template v-slot:cell(name)="row">
                        <router-link :to="{ name: 'hq', params: { id: row.item.id } }">{{ row.item.name }}</router-link>
                    </template>

                    <template v-slot:cell(actions)="row">
                        <b-button size="sm" class="btn-info" :to="{ name: 'menu', params: { id: row.item.id } }" title="Menu items">
                            <i class="fas fa-scroll"></i>
                            <span class="sr-only">Menu items</span>
                        </b-button>

                        <b-link class="btn btn-sm btn-info" :to="{ name: 'summary', params: { id: row.item.id } }" title="Sales overview">
                            <i class="fas fa-chart-line"></i>
                            <span class="sr-only">Sales overview</span>
                        </b-link>

                        <b-link class="btn btn-sm btn-success" :href="row.item.order_url" target="_blank" title="Client panel">
                            <i class="fas fa-user"></i>
                            <span class="sr-only">Client panel</span>
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

            <b-col lg="4">
                <b-card :title="(model.id ? 'Edit event ID#' + model.id : 'New event')">
                    <form @submit.prevent="save">
                        <b-form-group label="Name">
                            <b-form-input type="text" v-model="model.name"></b-form-input>
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

            console.log(window.ORGANISATION_ID);
            this.service = new EventService(window.ORGANISATION_ID); // hacky hacky
            this.refreshEvents();

        },

        data() {
            return {
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
                        label: 'Event name',
                    },
                    {
                        key: 'is_selling',
                        label: 'Status',
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

                this.items = (await this.service.index()).items;
                this.loaded = true;

            },

            async save() {

                this.saving = true;
                if (this.model.id) {
                    await this.service.update(this.model.id, this.model);
                } else {
                    await this.service.create(this.model);
                }

                this.model = {};
                this.saving = false;
                this.saved = true;

                setTimeout(
                    () => {
                        this.saved = false;
                    },
                    2500
                );

                this.refreshEvents();

            },

            async edit(model, index) {

                this.model = Object.assign({}, model);

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

            resetForm() {
                this.model = {};
            }
        }
    }
</script>
