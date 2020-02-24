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

        <div v-if="loaded">

            <h1>Settings</h1>

            <b-form @submit="onSubmit" @reset="onReset">

                <b-form-fieldset>
                    <legend>General settings</legend>

                    <b-form-group
                        id="terminal-name-group"
                        label="Terminal name"
                        label-for="terminal-name"
                        description="Describing name of this specific terminal."
                    >
                        <b-form-input
                            id="terminal-name"
                            v-model="name"
                            type="text"
                            required
                            placeholder="Terminal name"
                        ></b-form-input>
                    </b-form-group>

                </b-form-fieldset>

                <b-form-fieldset>
                    <legend>Cashless system</legend>

                    <b-form-group
                        id="nfc-server-group"
                        label="NFC webserver url"
                        label-for="nfc-server"
                    >
                        <b-form-input
                            id="nfc-server"
                            v-model="nfcServer"
                            type="text"
                            placeholder="NFC Server url"
                        ></b-form-input>
                    </b-form-group>

                    <b-form-group
                        id="nfc-password-group"
                        label="NFC webserver password"
                        label-for="nfc-server"
                    >
                        <b-form-input
                            id="nfc-password"
                            v-model="nfcPassword"
                            type="text"
                            placeholder="NFC Server password"
                        ></b-form-input>
                    </b-form-group>

                </b-form-fieldset>

                <b-button type="submit" variant="primary">Save</b-button>
                <b-button type="reset" variant="danger">Reset</b-button>
            </b-form>

        </div>

    </b-container>

</template>

<script>

    import {SettingService} from "../services/SettingService";

    export default {

        props: [

        ],


        async mounted() {

            this.settingService = this.$settingService;

            this.settingService.load()
                .then(function() {
                    this.onReset();
                    this.loaded = true;
                }.bind(this));
        },

        data() {
            return {
                loaded: false,
                name: '',
                nfcServer: '',
                nfcPassword: ''
            }
        },

        watch: {



        },

        methods: {

            onSubmit(evt) {
                evt.preventDefault();

                this.settingService.terminalName = this.name;
                this.settingService.nfcServer = this.nfcServer;
                this.settingService.nfcPassword = this.nfcPassword;

                this.settingService.save()
                    .then(function() {
                        window.location.reload();
                    })

            },

            onReset(evt = null) {
                if (evt) {
                    evt.preventDefault();
                }

                this.name = this.settingService.terminalName;
                this.nfcServer = this.settingService.nfcServer;
                this.nfcPassword = this.settingService.nfcPassword;
            }

        }
    }
</script>
