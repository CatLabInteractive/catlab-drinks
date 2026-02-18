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
            <b-col cols="12" id="order-history">

                <div class="text-center" v-if="!loaded">
                    <b-spinner :label="$t('Loading data')" />
                </div>

                <div v-if="loaded">

                    <b-link class="btn btn-success" :to="{ name: 'checkIn', params: { id: this.eventId } }" :title="$t('Check-In')">
                        <span>🛂</span>
                        {{ $t('Check-In') }}
                    </b-link>

                    <h2>{{ $t('Set attendees {name}', { name: event.name }) }}</h2>
                    <form @submit.prevent="replaceAttendees">
                        <div class="alert alert-danger">
                            {{ $t('This will remove ALL existing attendees and replace them with new ones.') }}
                        </div>

                        <b-form-group :label="$t('Replace attendees')">
                            <b-textarea v-model="attendeeInput" :placeholder="'alias-1: Name of attendee 1\tEmail address (optional)\nalias-2: Name of attendee 2\tEmail address (optional)\n...'" rows="10"></b-textarea>
                        </b-form-group>

                        <div>
                            <b-btn type="submit" variant="success">{{ $t('Save') }}</b-btn>
                            <b-btn type="button" variant="light" @click="resetForm()">{{ $t('Reset') }}</b-btn>

                            <b-alert v-if="saving" variant="none" show>{{ $t('Saving') }}</b-alert>
                            <b-alert v-if="saved" variant="none" show="2">{{ $t('Saved') }}</b-alert>
                        </div>
                    </form>

                </div>

            </b-col>
        </b-row>

    </b-container>

</template>

<script>

    import {EventService} from "../services/EventService";

    export default {
        mounted() {

            this.service = new EventService(window.ORGANISATION_ID); // hacky hacky

            this.eventId = this.$route.params.id;
            this.refresh();

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
                loaded: false,
                eventId: null,
                event: null,
                attendeeInput: '',
                saving: false,
                saved: false
            }
        },

        methods: {

            async refresh() {
                this.loaded = false;

                if (this.eventId) {
                    this.event = await this.service.get(this.eventId);
                    this.loaded = true;
                }
            },

            async replaceAttendees() {

                this.saving = true;

                await this.service.importAttendees(this.event.id, this.attendeeInput);

                this.resetForm();
                this.saving = false;
                this.saved = true;

                setTimeout(
                    () => {
                        this.saved = false;
                    },
                    2500
                );

            },

            resetForm() {
                this.attendeeInput = '';
            }
        }
    }
</script>
