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

        <b-row>
            <b-col cols="8" id="live-orders">

                <live-sales v-bind:eventId="eventId"></live-sales>

            </b-col>

            <b-col cols="4" class="remote-orders">

                <remote-orders v-bind:eventId="eventId"></remote-orders>

            </b-col>
        </b-row>

    </b-container>

</template>

<script>

    import {MenuService} from "../services/MenuService";

    export default {
        mounted() {

            this.eventId = this.$route.params.id;
            this.service = new MenuService(this.eventId);
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
                eventId: null,
                loaded: false,
                saving: false,
                saved: false,
                toggling: null,
                items: [],
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