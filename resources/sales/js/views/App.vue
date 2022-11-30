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

        <b-navbar toggleable="lg">
            <b-navbar-brand href="#">CatLab Drinks</b-navbar-brand>

            <b-navbar-toggle target="nav_collapse" />

            <b-collapse is-nav id="nav_collapse">
                <b-navbar-nav>

                    <b-nav-item :to="{ name: 'events' }"  v-if="!kioskMode">Events</b-nav-item>

                    <nfc-card-balance></nfc-card-balance>

                </b-navbar-nav>

                <!-- Right aligned nav items -->
                <b-navbar-nav class="ml-auto"  v-if="!kioskMode">

                    <b-navbar-nav>

                        <b-nav-item :to="{ name: 'cards' }">Cards</b-nav-item>
                        <b-nav-item :to="{ name: 'settings' }">Settings</b-nav-item>

                        <li class="nav-item">
                            <logout-link />
                        </li>

                    </b-navbar-nav>

                    <!--
                    <b-nav-form>
                        <b-form-input size="sm" class="mr-sm-2" type="text" placeholder="Search" />
                        <b-button size="sm" class="my-2 my-sm-0" type="submit">Search</b-button>
                    </b-nav-form>

                    <b-nav-item-dropdown text="Lang" right>
                        <b-dropdown-item href="#">EN</b-dropdown-item>
                        <b-dropdown-item href="#">ES</b-dropdown-item>
                        <b-dropdown-item href="#">RU</b-dropdown-item>
                        <b-dropdown-item href="#">FA</b-dropdown-item>
                    </b-nav-item-dropdown>

                    <b-nav-item-dropdown right>
                        <template v-slot:cell(button-content)="row"><em>User</em></template>
                        <b-dropdown-item href="#">Profile</b-dropdown-item>
                        <b-dropdown-item href="#">Signout</b-dropdown-item>
                    </b-nav-item-dropdown>
                    -->
                </b-navbar-nav>
            </b-collapse>
        </b-navbar>

        <router-view></router-view>
        <payment-popup></payment-popup>

    </div>

</template>
<script>
    export default {
        data() {
            return {
                kioskMode: false
            }
        },

        destroyed() {
            this.eventListeners.forEach(e => e.unbind());
        },

        mounted() {

            this.eventListeners = [];

            this.kioskMode = this.$kioskModeService.kioskModeActive;
            this.eventListeners.push(this.$kioskModeService.on('kioskmode:change', () => {
                this.kioskMode = this.$kioskModeService.kioskModeActive;
            }));
        }
    }
</script>
