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

    <!-- Modal Component -->
    <b-modal ref="paymentModal" class="payment-modal" title="Payment" @ok="cancel" @hide="cancel" button-size="lg" ok-only no-close-on-backdrop ok-variant="danger" ok-title="Cancel">

        <div v-if="loading" class="text-center">
            <b-spinner />
        </div>
        <div v-if="!loading">
            <p class="text-center" v-html="instructions"></p>

            <p v-if="error" class="text-center alert alert-warning">Card error: {{ error }}</p>

            <p class="text-center">
                <button v-if="$paymentService.allow_cash_payments" class="btn btn-success" v-on:click="cash()">
                    <i class="fas fa-money-bill-wave"></i>
                    {{cashAmount}}
                </button>

                <button v-if="$paymentService.allow_voucher_payment" class="btn btn-success" v-on:click="vouchers()">
                    <i class="fa fa-ticket-alt"></i>
                    {{voucherAmount}} vouchers
                </button>
            </p>
        </div>

    </b-modal>

</template>
<script>
    export default {

        destroyed() {
            this.eventListeners.forEach(e => e.unbind());
        },

        mounted() {

            this.eventListeners = [];

            this.active = false;
            this.eventListeners.push(this.$paymentService.on('transaction:start', (transaction) => {
                this.active = true;
                this.$refs.paymentModal.show();

                this.amount = (transaction.price / 100).toFixed(2);
                this.transaction = transaction;
                this.error = transaction.error;
                this.loading = transaction.loading;

                this.cashAmount = '€' + this.amount;
                this.voucherAmount = Math.ceil(this.amount / this.$paymentService.voucher_value);

                this.instructions = this.getInstructions();
            }));

            this.eventListeners.push(this.$paymentService.on('transaction:change', (transaction) => {
                //console.log(transaction.error);
                this.error = transaction.error;
                this.loading = transaction.loading;
            }));

            this.eventListeners.push(this.$paymentService.on('transaction:done', () => {
                this.active = false;
                this.$refs.paymentModal.hide();
                this.error = null;
                this.loading = false;
            }));
        },

        data() {
            return {
                amount: 0,
                error: null,
                loading: false,
                instructions: null
            };
        },

        methods: {

            getInstructions() {
                let paymentMethods = [];

                if (this.$paymentService.allow_nfc_payments) {
                    paymentMethods.push('<strong>Scan card</strong>');
                }

                if (this.$paymentService.allow_cash_payments) {
                    paymentMethods.push('Collect <strong>' + this.cashAmount + '</strong>');
                }

                if (this.$paymentService.allow_voucher_payment) {
                    paymentMethods.push('Collect <strong>' + this.voucherAmount + ' vouchers</strong>');
                }

                if (paymentMethods.length > 0) {
                    return paymentMethods.join(' or ');
                } else {
                    return '<p class="alert alert-danger">No payment methods have been enabled for this event.<br />Please edit the event and enable payment methods.</p>';
                }
            },

            async cancel() {

                if (!this.active) {
                    return;
                }

                //this.$refs.paymentModal.hide();
                this.$paymentService.cancel();
            },

            async cash() {
                this.$paymentService.cash();
            },

            async vouchers() {
                this.$paymentService.vouchers();
            }
        }
    }
</script>
