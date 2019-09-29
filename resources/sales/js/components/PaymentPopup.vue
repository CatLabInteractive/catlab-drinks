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
    <b-modal ref="paymentModal" class="order-confirm-modal" title="Betalen" @hide="cancel" button-size="lg" ok-only ok-variant="danger" ok-title="Cancel">
        <p>Scan card to spend {{ amount }}</p>

        <p v-if="error">{{ error }}</p>
    </b-modal>

</template>
<script>
    export default {
        mounted() {

            this.$paymentService.on('transaction:start', (transaction) => {
                this.$refs.paymentModal.show();

                this.amount = (transaction.price / 100).toFixed(2);
                this.transaction = transaction;
            });

            this.$paymentService.on('transaction:change', (transaction) => {
                console.log(transaction.error);
                this.error = transaction.error;
            });

            this.$paymentService.on('transaction:done', () => {
                this.$refs.paymentModal.hide();
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

                console.log('Cancel transaction');

                //this.$refs.paymentModal.hide();
                this.$paymentService.cancel();
            },
        }
    }
</script>
