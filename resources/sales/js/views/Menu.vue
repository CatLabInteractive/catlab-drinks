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

        <h1>Menu</h1>
        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <b-row>
            <b-col>
                <b-table striped hover :items="items" :fields="fields" v-if="loaded">

                    <template v-slot:cell(actions)="row">
                        <b-button size="sm" class="" @click="edit(row.item, row.index)">
                            <i class="fas fa-edit"></i>
                            <span class="sr-only">Edit</span>
                        </b-button>
                        <b-button size="sm" @click="remove(row.item)" class="btn-danger">
                            <i class="fas fa-trash"></i>
                            <span class="sr-only">Delete</span>
                        </b-button>
                    </template>

                    <template v-slot:cell(is_selling)="row">
                        <b-button v-if="!row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)" class="btn-danger">
                            Not selling
                        </b-button>

                        <b-button v-if="row.item.is_selling" size="sm" @click="toggleIsSelling(row.item)" class="btn-success">
                            Selling
                        </b-button>

                        <b-spinner v-if="toggling === row.item.id" small></b-spinner>
                    </template>

                </b-table>
            </b-col>

            <b-col lg="4">
                <b-card :title="(model.id ? 'Edit item ID#' + model.id : 'New item')">
                    <form @submit.prevent="save">
                        <b-form-group label="Name">
                            <b-form-input type="text" v-model="model.name"></b-form-input>
                        </b-form-group>

                        <b-form-group label="Description">
                            <b-form-input type="text" v-model="model.description"></b-form-input>
                        </b-form-group>

                        <b-form-group label="Price">
                            <b-form-input type="number" v-model="model.price" step=".01"></b-form-input>
                        </b-form-group>

                        <div>
                            <b-btn type="submit" variant="success">Save</b-btn>
                            <b-btn type="button" variant="light" @click="resetForm()">Reset</b-btn>

                            <b-alert v-if="saving" variant="none" show>Saving</b-alert>
                            <b-alert v-if="saved" variant="none" :show="2">Saved</b-alert>
                        </div>
                    </form>
                </b-card>
            </b-col>
        </b-row>

    </b-container>

</template>

<script>

    import {MenuService} from "../services/MenuService";

    export default {
        mounted() {

            this.service = new MenuService(this.$route.params.id);
            this.refresh();

        },

        watch: {
            '$route' (to, from) {
                // react to route changes...
                this.service = new MenuService(to.params.id);
                this.refresh();
            }
        },

        data() {
            return {
                loaded: false,
                saving: false,
                saved: false,
                toggling: null,
                items: [],
                fields: [
                    {
                        key: 'id',
                        label: '#'
                    },
                    {
                        key: 'name',
                        label: 'Product name',
                    },
                    {
                        key: 'price',
                        label: 'Price',
                        class: 'text-center'
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

            async refresh() {

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

                this.refresh();

            },

            async edit(model, index) {

                this.model = Object.assign({}, model);

            },

            async remove(model) {

                if (confirm('Are you sure you want to remove this menu item?')) {
                    if (this.model.id === model.id) {
                        this.model = {};
                    }

                    await this.service.delete(model.id);
                    await this.refresh();
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
