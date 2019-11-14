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
            <div class="alert alert-danger col-lg-6" role="alert">
                <p>
                    This card is corrupted, it might belong to a different organisation. If you are sure the card
                    should be correct, you can try to rebuild the card data from the available online data.
                    Note that if you have bars that are opperating online, you might lose transactions
                    and the balance might not be correct after rebuilding.
                </p>
                <button v-on:click="rebuild()" class="btn btn-danger">Rebuild</button>
            </div>
        </div>

        <div v-if="card.ready" class="row">

            <div class="col-md-8">
                <h2>Topup</h2>
                <div class="topup-amounts">
                    <div v-for="amount in defaultAmounts" v-on:click="topupForAmount(amount)" class="amount">
                        €{{ amount.toFixed(2) }}
                    </div>
                </div>

                <div style="clear: both;"></div>

                <h3>Custom amount</h3>
                <label for="customAmount">Custom amount</label><br />
                <input type="number" step="0.01" placeholder="10.00" id="customAmount" v-model="topupAmountString" />

                <button class="btn btn-primary" v-on:click="topup()">Topup</button>

                <h3>Aliases</h3>
                <ul>
                    <li v-for="alias in card.orderTokenAliases">{{alias}} <a href="javascript:void(0);" v-on:click="removeOrderTokenAlias(alias)" class="btn btn-danger btn-sm">x</a></li>

                    <li>
                        <input type="text" v-model="creatingOrderTokenAlias" />
                        <button class="btn btn-primary btn-sm" v-on:click="addOrderTokenAlias">Add</button>
                    </li>
                </ul>

                <h3>Discount</h3>
                <p>
                    Card gives <input type="number" step="1" min="0.0" max="100.0" v-model="card.discountPercentage" />% at all sales.
                    <button class="btn btn-primary btn-sm" v-on:click="saveCardData">Save</button>
                </p>

                <p>
                    <span v-if="storeState === 'storing'">Saving</span>
                    <span v-if="storeState === 'stored'">Saved</span>
                </p>
            </div>

            <div class="col-md-4">

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
                        <td>{{ card.getLastTransactionDate() | formatDate }}</td>
                    </tr>

                </table>

                <h3>Transactions</h3>
                <transactions-table v-if="loaded" :cardId="card.id" />

                <p><button v-on:click="rebuild()" class="btn btn-danger btn-sm">Rebuild</button></p>

            </div>
        </div>

        <!-- Modal Component -->
        <b-modal ref="confirmModal" class="order-confirm-modal" title="Topup bevestigen" @ok="confirmTopup" @cancel="cancelTopup" button-size="lg" no-close-on-backdrop>
            <p>Are you sure you want to topup for <strong>€{{ topupAmount.toFixed(2) }}</strong>?</p>
        </b-modal>

        <!-- Modal Component -->
        <b-modal ref="processingModal" title="Even wachten" no-close-on-esc no-close-on-backdrop hide-footer hide-header>
            <div class="text-center">
                <b-spinner />
            </div>
        </b-modal>

        <!-- Modal Component -->
        <b-modal ref="processedModal" class="order-confirm-modal" ok-only button-size="lg" title="Topup successful" ok-variant="success" no-close-on-backdrop>
            <p class="text-center"><i class="fas fa-thumbs-up huge"></i></p>
            <p class="text-center alert alert-success">Topup successful.</p>
        </b-modal>

        <!-- Modal Component -->
        <b-modal ref="declinedModal" class="order-confirm-modal" ok-only button-size="lg" title="Topup failed" ok-variant="danger" no-close-on-backdrop>
            <p class="text-center"><i class="fas fa-exclamation-triangle huge"></i></p>
            <p class="text-center alert alert-danger">Topup failed: {{ error }}</p>
        </b-modal>
    </div>

</template>
<script>

    import {CardValidationException} from "../nfccards/exceptions/CardValidationException";

    const uuidv1 = require('uuid/v1');

    export default {

        props: [
            'card'
        ],

        data() {
            return {
                canTopup: false,
                loaded: false,
                transactions: [],
                topupAmount: 10,
                topupAmountString: '',
                creatingOrderTokenAlias: '',
                storeState: null,
                error: null,
                orderDetails: null,
                defaultAmounts: [
                    5,
                    10,
                    15,
                    20,
                    25,
                    30,
                    35,
                    45,
                    50,
                    75,
                    100
                ]
            }
        },

        mounted() {

            this.transactions = [];
            this.storingAlias = false;
            if (this.card) {
                if (this.card.ready) {
                    this.loadCard();
                } else {
                    this.card.on('ready', () => {
                        this.loadCard();
                    });
                }
            }

        },

        methods: {

            async loadCard() {

                this.loaded = true;

                this.resetTopupAmount();

            },

            async rebuild() {

                if (confirm('Danger! Rebuilding will only keep all transactions that are available online. Are you sure you want to do that?')) {

                    try {
                        console.log('Rebuilding card');
                        await this.$cardService.rebuild(this.card);
                        console.log('Done rebuilding card');

                        alert('Card is rebuilt from online data.');
                    } catch (e) {
                        console.error(e);
                        alert('Rebuild error: ' + e.message);
                    }
                }
            },

            async topup() {

                this.topupAmount = parseFloat(this.topupAmountString);
                this.$refs.confirmModal.show();

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
                await this.$cardService.saveCardAliases(this.card);
                this.storeState = 'stored';
                setTimeout(() => {
                    this.storeState = null;
                }, 2000);
            },

            async topupForAmount(amount) {
                this.topupAmount = amount;
                this.$refs.confirmModal.show();
            },

            async confirmTopup() {

                this.$refs.confirmModal.hide();
                this.error = null;

                const amount = Math.floor(this.topupAmount * 100);
                const uniqueId = uuidv1();

                // we probably want to store this somewhere, but hey... no time.
                try {
                    this.$refs.processingModal.show();

                    // do the actual topup
                    await this.$cardService.topup(uniqueId, amount);

                    // reload last transactions (as otherwise it might be confusing)
                    this.transactions = await this.$cardService.getTransactions(this.card.id);

                    this.$refs.processingModal.hide();

                    this.$refs.processedModal.show();
                    setTimeout(() => {
                        if (!this.$refs.processedModal) {
                            return;
                        }
                        this.$refs.processedModal.hide();
                    }, 5000);

                } catch (e) {
                    this.$refs.processingModal.hide();

                    this.error = e.message;
                    this.$refs.declinedModal.show();
                    //alert(e.message);
                }
            },

            async cancelTopup() {
                this.$refs.confirmModal.hide();
                this.resetTopupAmount();
            },

            async saveCardData() {
                this.storeState = 'storing';

                try {
                    await this.card.validate();
                } catch (e) {
                    if (e instanceof CardValidationException) {
                        alert('Validation error: ' + e.message);
                    } else {
                        throw e;
                    }
                }

                // first on the card
                await this.card.save();

                // then online
                await this.$cardService.uploadCardData(this.card);

                this.storeState = 'stored';

                setTimeout(() => {
                    this.storeState = null;
                }, 2000);
            },

            resetTopupAmount() {
                this.topupAmount = 10;
                this.topupAmountString = '10.00';
            },

            showOrder(order) {
                console.log(order);

                this.orderDetails = order;
                this.$refs.orderModal.show();
            }
        }
    }
</script>
