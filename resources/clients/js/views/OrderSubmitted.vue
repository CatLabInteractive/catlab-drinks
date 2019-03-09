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

        <b-row>
            <b-col>
                <h3>We hebben je bestelling ontvangen!</h3>
                <p>Wacht even, we komen er aan!</p>

                <p>
                    <b-button class="btn-success" :to="{ name: 'order' }">
                        Nieuwe bestelling
                    </b-button>
                </p>
            </b-col>
        </b-row>

    </b-container>

</template>

<script>

    export default {
        mounted() {

            window.scrollTo(0,0);

        },

        data() {
            return {

            }
        },

        methods: {



        }
    }
</script>