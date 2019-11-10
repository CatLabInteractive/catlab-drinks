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

        <p v-if="card">
            <a class="btn btn-danger" @click="startTest()" v-if="!running">Start test</a>
            <a class="btn btn-danger" @click="stopTest()" v-if="running">Stop test</a>
        </p>

        <ul>
            <li v-for="log in logs">
                {{ log.description }}
            </li>
        </ul>

        <p v-if="!card">Scan card to start test</p>

    </b-container>

</template>

<script>

    export default {
        mounted() {
            this.$cardService.on('card:connect', (card) => {
                this.setCard(card);
            });
        },

        methods: {
            async startTest() {
                if (confirm('Warning! This will apply random transactions to any card that is presented. Do you want to continue?')) {

                    try {
                        this.running = true;
                        while (this.running) {

                            const currentBalance = this.card.getBalance();
                            const amount = Math.ceil(Math.random() * 10);
                            const out = await this.$cardService.spend(null, amount);
                            this.logs.push({
                                description: 'Current balance: ' + currentBalance + ', withdrawing ' + amount
                            });

                            await this.sleep(Math.random() * 500);

                        }
                    } catch (e) {
                        console.error(e);
                        this.running = false;
                    }
                }
            },

            async stopTest() {
                this.running = false;
            },

            async sleep(duration) {
                return new Promise(
                    (resolve, reject) => {
                        setTimeout(resolve, duration);
                    }
                );
            },

            setCard(card) {
                this.card = card;
            }
        },

        data() {
            return {
                card: null,
                logs: [],
                running: false
            };
        }
    }
</script>
