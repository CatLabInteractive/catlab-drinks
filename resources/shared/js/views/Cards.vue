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

        <h1>
            {{ $t('Card Management') }}
            <b-link class="btn btn-sm btn-info" :to="{ name: 'transactions' }">
                {{ $t('Transactions') }}
            </b-link>
        </h1>

        <div class="text-center" v-if="loading">
            <b-spinner :label="$t('Loading data')" />
        </div>

        <div v-if="error">
            <div class="alert alert-danger" role="alert">
                {{ error }}
            </div>
        </div>

        <div v-if="loaded">


            <div v-if="card === null">
                <p>{{ $t('Scan to start') }}</p>
            </div>

            <div v-if="card">

                <card :card="card"></card>

            </div>

        </div>

    </b-container>

</template>

<script>

    import Card from "../components/Card.vue";
	import {OrganisationService} from "../services/OrganisationService";

    export default {

		components: {
			'card': Card,
		},

        props: [

        ],

        unmounted() {
            this.eventListeners.forEach(e => e.unbind());
        },

        async mounted() {

            this.eventListeners = [];

            // do we have a card service?
            this.error = null;
            if (!this.$cardService || !this.$cardService.hasCardReader) {
                this.error = this.$t('No NFC card service found. Please check the settings.');
                this.loading = false;
                return;
            }

            // load event
            this.$cardService.setSkipRefreshWhenBadInternetConnection(false);

            this.loaded = true;
            this.loading = false;

            if (this.$cardService.currentCard) {
                this.showCard(this.$cardService.getCard());
            }

            this.eventListeners.push(this.$cardService.on('card:connect', (card) => {
                this.showCard(card);
            }));

            this.eventListeners.push(this.$cardService.on('card:disconnect', (card) => {
                this.hideCard(card);
            }));
        },

        data() {
            return {
                error: null,
                organisation: null,
                loading: true,
                loaded: false,
                card: null
            }
        },

        watch: {



        },

        methods: {

            async showCard(card) {

                this.card = card;

                //console.log(this.transactions);
            },

            async hideCard() {
                this.card = null;
            }

        }
    }
</script>
