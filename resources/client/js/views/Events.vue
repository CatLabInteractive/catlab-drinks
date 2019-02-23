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

                    <template slot="actions" slot-scope="row">
                        <b-button size="sm" class="mr-1">
                            Info modal
                        </b-button>
                        <b-button size="sm" >
                            Details
                        </b-button>
                    </template>

                </b-table>
            </b-col>

            <b-col lg="4">
                <b-card :title="(model.id ? 'Edit event ID#' + model.id : 'New event')">
                    <form @submit.prevent="saveEvent">
                        <b-form-group label="Name">
                            <b-form-input type="text" v-model="model.name"></b-form-input>
                        </b-form-group>

                        <div>
                            <b-btn type="submit" variant="success">Save Post</b-btn>
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

            this.service = new EventService();
            this.refreshEvents();

        },

        data() {
            return {
                loaded: false,
                items: [

                ],
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
                        key: 'actions',
                        label: 'Actions'
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

            async saveEvent() {

                await this.service.create(this.model);
                this.refreshEvents();


            }
        }
    }
</script>