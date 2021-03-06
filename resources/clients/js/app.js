/*
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require('./bootstrap');

import Vue from "vue";
import VueRouter from "vue-router";
import BootstrapVue from "bootstrap-vue";
import App from './views/App'
import Order from './views/Order'
import AirbrakeClient from 'airbrake-js';
import VueCookies from 'vue-cookies'


if (AIRBRAKE_CONFIG) {
    var airbrake = new AirbrakeClient(AIRBRAKE_CONFIG);
    Vue.config.errorHandler = function (err, vm, info) {
        airbrake.notify({
            error: err,
            params: {info: info}
        });
    };
}

Vue.use(VueRouter);
Vue.use(BootstrapVue);
Vue.use(VueCookies);
Vue.$cookies.config('7d');


/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */
const router = new VueRouter({
    mode: 'history',
    base: BASE_URL,
    routes: [
        {
            path: '/',
            name: 'order',
            component: Order,
            default: true
        }
    ],
});

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: '#app',
    components: { App },
    router
});
