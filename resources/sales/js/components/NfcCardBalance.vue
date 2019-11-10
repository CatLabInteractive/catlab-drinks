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

    <form class="form-inline">

        <span v-if="connected === true" class="btn btn-sm btn-success">NFC</span>
        <span v-if="connected === false" class="btn btn-sm btn-danger">NFC</span>
        &nbsp;<span v-if="apiConnected === false" class="btn btn-sm btn-danger">API Offline</span>

        &nbsp;<span v-if="!corrupt && balance !== null" class="btn btn-sm btn-warning">Balance: {{ balance }}</span>
        <span v-if="corrupt" class="btn btn-sm btn-danger">Corrupt card, contact support</span>
        <b-spinner v-if="loading" small />
    </form>

</template>
<script>
    export default {
        mounted() {

            if (!this.$cardService || !this.$cardService.hasCardReader) {
                return;
            }

            this.connected = this.$cardService.isConnected();
            this.apiConnected = this.$cardService.hasApiConnection();

            this.$cardService.on('connection:change', function(isOnline) {
                //console.log('is online', isOnline);
                this.connected = isOnline;
            }.bind(this));

            this.$cardService.on('apiConnection:change', function(isOnline) {
                //console.log('is online', isOnline);
                this.apiConnected = this.$cardService.hasApiConnection();
            }.bind(this));

            this.$cardService.on('card:connect', function(card) {
                this.loading = true;
            }.bind(this));

            this.$cardService.on('card:corrupt', function(card) {
                this.corrupt = card.isCorrupted();
                this.loading = false;
            }.bind(this));

            this.$cardService.on('card:balance:change', function(card) {
                this.balance = card.getVisibleBalance();
                this.loading = false;
            }.bind(this));

            this.$cardService.on('card:disconnect', function(card) {
                this.balance = null;
                this.corrupt = false;
                this.loading = false;
            }.bind(this));

        },

        data() {
            return {
                visible: false,
                balance: null,
                connected: null,
                apiConnected: null,
                corrupt: false,
                loading: false
            };
        }
    }
</script>
