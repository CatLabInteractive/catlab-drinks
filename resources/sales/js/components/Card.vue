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

    <div v-if="card">

        <h2>Card #<strong>{{ card.uid }}</strong></h2>
        <div v-if="card.corrupted">
            <div class="alert alert-danger" role="alert">
                This card is corrupted.
                <button v-on:click="rebuild()">Rebuild</button>
            </div>
        </div>

        <div v-if="card.loaded" class="row">

            <div class="col-md-6">
                <h2>Topup</h2>
                <label for="customAmount">Custom amount</label><br />
                <input type="number" min="0" step="0.01" placeholder="10.00" id="customAmount" v-model="topupAmount" />

                <button class="btn btn-primary" v-on:click="topup()">Topup</button>

                <h3>Aliases</h3>
                <ul>
                    <li v-for="alias in card.orderTokenAliases">{{alias}} <a href="javascript:void(0);" v-on:click="removeOrderTokenAlias(alias)" class="btn btn-danger btn-sm">x</a></li>

                    <li>
                        <input type="text" v-model="creatingOrderTokenAlias" />
                        <button class="btn btn-primary btn-sm" v-on:click="addOrderTokenAlias">Add</button>
                    </li>
                </ul>

                <p>
                    <span v-if="storeState === 'storing'">Saving</span>
                    <span v-if="storeState === 'stored'">Saved</span>
                </p>
            </div>

            <div class="col-md-6">

                <table class="table">

                    <tr>
                        <td>ID</td>
                        <td>{{ card.id }}</td>
                    </tr>

                    <tr>
                        <td>Balance</td>
                        <td>{{ card.getVisibleBalance() }}</td>
                    </tr>

                    <tr>
                        <td>Last transaction</td>
                        <td>{{ card.getLastTransactionDate().toISOString() }}</td>
                    </tr>

                </table>

                <h3>Transactions</h3>
                <table class="table">

                    <tr v-for="transaction in this.transactions">

                        <td>{{ transaction.transactionId }}</td>
                        <td>{{ transaction.getVisibleAmount() }}</td>
                        <td>{{ transaction.type }}</td>
                        <td>{{ transaction.date ? transaction.date.toISOString() : '' }}</td>

                    </tr>

                </table>

                <p><button v-on:click="rebuild()" class="btn btn-danger btn-sm">Rebuild</button></p>

            </div>
        </div>
    </div>

</template>
<script>

    const uuidv1 = require('uuid/v1');

    export default {

        props: [
            'card'
        ],

        data() {
            return {
                transactions: [],
                topupAmount: 10,
                creatingOrderTokenAlias: '',
                storeState: null
            }
        },

        mounted() {

            this.transactions = [];
            this.storingAlias = false;
            if (this.card) {
                if (this.card.loaded) {
                    this.loadCard();
                } else {
                    this.card.on('loaded', () => {
                        this.loadCard();
                    });
                }
            }

        },

        methods: {

            async loadCard() {

                this.transactions = await this.$cardService.getTransactions(this.card);
                console.log(this.transactions);

            },

            async rebuild() {

                if (confirm('Danger! Rebuilding will only keep all transactions that are available online. Are you sure you want to do that?')) {

                    try {
                        console.log('Rebuilding card');
                        await this.$cardService.rebuild(this.card);
                        console.log('Done rebuilding card');
                    } catch (e) {
                        console.error(e);
                        alert('Rebuild error: ' + e.message);
                    }
                }
            },

            async topup() {
                const amount = Math.floor(this.topupAmount * 100);

                const uniqueId = uuidv1();

                // we probably want to store this somewhere, but hey... no time.
                await this.$cardService.topup(uniqueId, amount);
                alert('topup succesful');
            },

            async addOrderTokenAlias() {
                this.card.orderTokenAliases.push(this.creatingOrderTokenAlias);
                this.creatingOrderTokenAlias = '';

                this.storeServerData();
            },

            async removeOrderTokenAlias(alias) {
                const index = this.card.orderTokenAliases.indexOf(alias);
                this.card.orderTokenAliases.splice(index, 1);

                this.storeServerData();
            },

            async storeServerData() {
                this.storeState = 'storing';
                this.$cardService.saveCardAliases(this.card);
                this.storeState = 'stored';
                setTimeout(() => {
                    this.storeState = null;
                }, 2000);
            }
        }
    }
</script>
