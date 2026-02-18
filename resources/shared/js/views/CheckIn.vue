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

        <b-row>
            <b-col cols="12" id="check-in">

                <h2>{{ $t('Check-In') }}</h2>
                <div v-if="error">
                    <div class="alert alert-danger" role="alert">
                        {{ error }}
                    </div>
                </div>

                <div v-if="loaded">

                    <div v-if="this.attendees.length === 0" class="alert alert-danger">
                        {{ $t('Please import attendees and aliases before using this module.') }}
                    </div>

                    <div v-if="card === null">
                        <p>{{ $t('Scan to start') }}</p>
                    </div>

                    <div v-if="card && !selectingAttendee">

                        <card :card="card"></card>

                    </div>

                    <div v-if="card && selectingAttendee">

                        <h3>{{ $t('Select attendee') }}</h3>
                        <p class="alert alert-danger">
                            {{ $t('Always checkin all attendees. If the attendee already has a card, please use that card instead of a new one.') }}
                        </p>

                        <div v-if="this.attendees.length === 0" class="alert alert-danger">
                            {{ $t('No attendees have been set. Please import attendees before using this module.') }}
                        </div>

                        <div v-for="(attendee, index) in attendees" v-if="!attendee.alreadySelected" class="attendee" v-on:click="selectAttendee(attendee)">
                            <span class="name">{{attendee.name}}</span>
                        </div>

                    </div>

                </div>

                <b-modal ref="confirmModal" class="check-in-confirm-modal" :title="$t('Confirm check-in')" @ok="confirmCheckIn()" @cancel="cancelCheckIn()" button-size="lg" no-close-on-backdrop>
                    <div v-if="attendeeCheckingIn" class="check-in-confirm">
                        <p class="name">{{ attendeeCheckingIn.name }}</p>
                        <p class="alias">{{ $t('Alias: {alias}', { alias: attendeeCheckingIn.alias }) }}</p>
                    </div>
                </b-modal>

            </b-col>
        </b-row>

    </b-container>

</template>

<script>

    import Card from "../components/Card.vue";
	import {EventService} from "../services/EventService";
    import {ExternalCheckinService} from "../services/ExternalCheckinService";
    import {EventListener} from "../utils/Eventable";

    export default {

		components: {
			'card': Card,
		},

        mounted() {

            this.eventService = new EventService(window.ORGANISATION_ID); // hacky hacky
            this.checkinService = new ExternalCheckinService();

            this.eventListeners = [];

            this.eventId = this.$route.params.id;
            this.refresh();

        },

        unmounted() {

            // we should unlisten the events here
            this.eventListeners.forEach(e => e.unbind());

        },

        watch: {
            '$route' (to, from) {
                // react to route changes...
                this.eventId = to.params.id;
                this.refresh();
            }
        },

        data() {
            return {
                event: null,
                eventId: null,
                error: null,
                organisation: null,
                loading: true,
                loaded: false,
                card: null,
                attendees: null,
                selectingAttendee: false,
                attendeeCheckingIn: null
            }
        },

        methods: {

            async refresh() {

                this.loading = true;
                this.loaded = false;

                // do we have a card service?
                this.error = null;
                if (!this.$cardService || !this.$cardService.hasCardReader) {
                    this.error = this.$t('No NFC card service found. Please check the settings.');
                    this.loading = false;
                    return;
                }

                // load event
                this.$cardService.setSkipRefreshWhenBadInternetConnection(false);

                this.event = await this.eventService.get(this.eventId);

                // load attendees
                this.attendees = (await this.eventService.getAttendees(this.event.id)).items;
                this.attendees.sort((a, b) => {
                    return a.name.localeCompare(b.name);
                });

                this.loaded = true;
                this.loading = false;

                if (this.$cardService.getCard()) {
                    this.showCard(this.$cardService.getCard());
                }

                this.eventListeners.push(
                    this.$cardService.on('card:loaded', (card) => {
                        this.showCard(card);
                    }
                ));

                this.eventListeners.push(
                    this.$cardService.on('card:disconnect', (card) => {
                        this.hideCard(card);
                    }
                ));
            },

            async showCard(card) {

                this.card = card;

                // should we select an attendee?
                this.selectingAttendee = !this.hasAssignedAttendee(this.card);
            },

            /**
             * Does this card already have an attendee assigned to it?
             * @param card
             * @returns {boolean}
             */
            hasAssignedAttendee(card) {

                for (let i = 0; i < this.card.orderTokenAliases.length; i ++) {
                    let existingAlias = this.card.orderTokenAliases[i];
                    for (let j = 0; j < this.attendees.length; j ++) {
                        if (existingAlias === this.attendees[j].alias) {
                            return true;
                        }
                    }
                }

                return false;
            },

            async selectAttendee(attendee) {

                this.attendeeCheckingIn = attendee;
                this.$refs.confirmModal.show();

            },

            async confirmCheckIn() {
                // and do the actual check-in
                this.selectingAttendee = false;
                this.loading = true;

                this.loading = false;
                this.loaded = false;

                let alias = this.attendeeCheckingIn.alias;
                this.card.addOrderTokenAlias(alias);

                await Promise.all([
                    this.$cardService.saveCardAliases(this.card),
                    this.callExternalCheckin(this.card)
                ]);

                // mark attendee as 'already selected'
                this.attendeeCheckingIn.alreadySelected = true;

                this.attendeeCheckingIn = null;
                this.$refs.confirmModal.hide();

                this.loaded = true;
                this.loading = false;
            },

            async cancelCheckIn() {
                this.attendeeCheckingIn = null;
                this.$refs.confirmModal.hide();
            },

            async hideCard() {
                this.card = null;
                this.selectingAttendee = false;
                this.attendeeCheckingIn = null;
                this.$refs.confirmModal.hide();
            },

            async callExternalCheckin(card) {
                return this.checkinService.checkin(this.event, this.attendeeCheckingIn, card);
            }
        }
    }
</script>
