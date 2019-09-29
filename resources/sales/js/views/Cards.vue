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

        <div class="text-center" v-if="loading">
            <b-spinner label="Loading data" />
        </div>

        <div v-if="error">
            <div class="alert alert-danger" role="alert">
                {{ error }}
            </div>
        </div>

        <div v-if="loaded">

            <h1>Card management</h1>
            <b-row>
                <b-col md="8">

                    <h2>Topup</h2>
                    <label for="customAmount">Custom amount</label><br />
                    <input type="number" min="0" step="0.01" placeholder="10.00" id="customAmount" v-model="topupAmount" />

                    <button class="btn btn-primary" v-on:click="topup()">Topup</button>

                </b-col>

                <b-col md="4">

                    <div v-if="card === null">
                        <p>Scan to start</p>
                    </div>

                    <div v-if="card">

                        <h2>Card #<strong>{{ card.uid}}</strong></h2>

                        <div v-if="card.corrupted">
                            <div class="alert alert-danger" role="alert">
                                This card is corrupted.
                                <button v-on:click="format()">Rebuild</button>
                            </div>
                        </div>

                        <div v-if="card.loaded">

                            <table class="table">

                                <tr>
                                    <td>Balance</td>
                                    <td>{{ card.getVisibleBalance() }}</td>
                                </tr>

                                <tr>
                                    <td>Last transaction</td>
                                    <td>{{ card.getLastTransactionDate().toISOString() }}</td>
                                </tr>

                            </table>

                            <button v-on:click="rebuild()">Rebuild</button>
                        </div>


                    </div>

                </b-col>
            </b-row>

        </div>

    </b-container>

</template>

<script>

    import {OrganisationService} from "../services/OrganisationService";
    import {CardService} from "../nfccards/CardService";
    import {Card} from "../nfccards/models/Card";

    export default {

        props: [

        ],


        async mounted() {

            // do we have a card service?
            this.error = null;
            if (!this.$cardService) {
                this.error = 'No NFC card service found. Please check the settings.';
                this.loading = false;
                return;
            }

            // load event
            this.organisationService = new OrganisationService(window.ORGANISATION_ID);

            this.organisation = await this.organisationService.get(window.ORGANISATION_ID, { fields: 'id,name,secret' });

            this.cardService = this.$cardService;

            this.cardService.setPassword(this.organisation.secret);

            this.loaded = true;
            this.loading = false;

            this.cardService.on('card:connect', (card) => {
                this.showCard(card);
            });

            this.cardService.on('card:loaded', (card) => {

                /*
                // increase balance
                console.log('card loaded');

                card.balance += 100;
                console.log('adding 100 balance: new balance = ' + card.balance);

                card.lastTransaction = new Date();

                card.save(card);
                 */

            });

            this.cardService.on('card:disconnect', (card) => {
                this.hideCard(card);
            });
        },

        data() {
            return {
                error: null,
                organisation: null,
                loading: true,
                loaded: false,
                card: null,
                tarnsactions: [],
                topupAmount: 10
            }
        },

        watch: {



        },

        methods: {

            async showCard(card) {

                this.transactions = await this.cardService.getTransactions(card);
                this.card = card;

                //console.log(this.transactions);
            },

            async hideCard() {
                this.card = null;
            },

            async rebuild() {

                if (confirm('Danger! Rebuilding will only keep all transactions that are available online. Are you sure you want to do that?')) {
                    console.log('Rebuilding card');
                    await this.cardService.rebuild(this.card);
                    console.log('Done rebuilding card');
                }
            },

            async topup() {
                const amount = Math.floor(this.topupAmount * 100);

                await this.cardService.topup('', amount);
                alert('topup succesful');
            }

        }
    }
</script>
