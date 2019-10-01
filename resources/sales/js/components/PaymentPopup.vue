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
    <b-modal ref="paymentModal" class="payment-modal" title="Betalen" @ok="cancel" @hide="cancel" button-size="lg" ok-only no-close-on-backdrop ok-variant="danger" ok-title="Cancel">

        <p class="text-center">
            Scan kaart of schrap <strong>{{Math.ceil(amount / 0.5)}} vakjes</strong>.
        </p>

        <p v-if="error" class="text-center alert alert-warning">Kaart fout: {{ error }}</p>

        <p class="text-center"><button class="btn btn-success" v-on:click="cash()">{{Math.ceil(amount / 0.5)}} vakjes geschrapt</button></p>

    </b-modal>

</template>
<script>
    export default {

        mounted() {

            this.active = false;
            this.$paymentService.on('transaction:start', (transaction) => {
                this.active = true;
                this.$refs.paymentModal.show();

                this.amount = (transaction.price / 100).toFixed(2);
                this.transaction = transaction;
                this.error = transaction.error;
            });

            this.$paymentService.on('transaction:change', (transaction) => {
                console.log(transaction.error);
                this.error = transaction.error;
            });

            this.$paymentService.on('transaction:done', () => {
                this.active = false;
                this.$refs.paymentModal.hide();
                this.error = null;
            });
        },

        data() {
            return {
                amount: 0,
                error: null
            };
        },

        methods: {
            async cancel() {

                if (!this.active) {
                    return;
                }

                //this.$refs.paymentModal.hide();
                this.$paymentService.cancel();
            },

            async cash() {
                this.$paymentService.cash();
            }
        }
    }
</script>
