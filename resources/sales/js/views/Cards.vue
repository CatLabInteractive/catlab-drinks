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

        <div class="text-center" v-if="!loaded">
            <b-spinner label="Loading data" />
        </div>

        <h1>Card management</h1>
        <div v-if="loaded">

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
                                <button v-on:click="format()">Format</button>
                            </div>
                        </div>

                        <div v-if="card.loaded">
                            <p>Last transaction: {{ card.getLastTransaction().toISOString()}}</p>
                            <p>Current balance: {{ card.getBalance() }}</p>
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

            // load event
            this.organisationService = new OrganisationService(window.ORGANISATION_ID);

            this.organisation = await this.organisationService.get(window.ORGANISATION_ID, { fields: 'id,name,secret' });

            this.cardService = this.$cardService;

            this.cardService.setPassword(this.organisation.secret);

            this.loaded = true;

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
                organisation: null,
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

            async format() {
                // reset the card to non corrupted state and write.
                console.log('Formatting card');

                this.card.balance = 0;
                this.card.transactionCount = 0;
                this.card.previousTransactions = [ 0, 0, 0, 0, 0 ];
                this.card.lastTransaction = new Date();

                await this.card.save();
                console.log('Done');
            },

            async topup() {
                const amount = Math.floor(this.topupAmount * 100);
                alert(amount);
            }

        }
    }
</script>
