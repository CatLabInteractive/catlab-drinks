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

    export default {
        mounted() {

            this.eventId = this.$route.params.id;
            this.refresh();

            this.$kioskModeService.enableKioskMode();

        },

        watch: {
            '$route' (to, from) {
                // react to route changes...
                this.eventId = to.params.id;
                this.refresh();
            }
        },

        data() {
            return {
                eventId: null
            }
        },

        methods: {

            async refresh() {


            }
        }
    }
</script>
