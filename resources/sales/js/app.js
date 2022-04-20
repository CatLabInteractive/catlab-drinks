
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

import Menu from "./views/Menu";

require('./bootstrap');

import Vue from "vue";
import VueRouter from "vue-router";
import BootstrapVue from "bootstrap-vue";
import moment from 'moment'
import AirbrakeClient from 'airbrake-js';

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

import App from './views/App'
import Hello from './views/Hello'
import Home from './views/Home'
import Events from './views/Events'
import Headquarters from "./views/Headquarters";
import Sales from "./views/Sales";
import SalesSummary from "./views/SalesSummary";
import Cards from "./views/Cards";
import {CardService} from "./nfccards/CardService";
import Settings from "./views/Settings";
import {SettingService} from "./services/SettingService";
import {PaymentService} from "./services/PaymentService";
import {OrganisationService} from "./services/OrganisationService";
import Transactions from "./views/Transactions";
import TestTransactions from "./views/TestTransactions";
import FinancialOverview from "./views/FinancialOverview";
import Attendees from "./views/Attendees";
import CheckIn from "./views/CheckIn";
import {KioskService} from "./services/KioskService";

Vue.component(
    'live-sales',
    require('./components/LiveSales.vue').default
);

Vue.component(
    'remote-orders',
    require('./components/RemoteOrders.vue').default
);

Vue.component(
    'relax',
    require('./components/Relax.vue').default
);

Vue.component(
    'remote-order-status',
    require('./components/RemoteOrderStatus.vue').default
);

Vue.component(
    'logout-link',
    require('./components/LogoutLink.vue').default
);

Vue.component(
    'sales-history',
    require('./components/SalesHistory.vue').default
);

Vue.component(
    'sales-summary',
    require('./components/SalesSummary.vue').default
);

Vue.component(
    'nfc-card-balance',
    require('./components/NfcCardBalance.vue').default
);

Vue.component(
    'payment-popup',
    require('./components/PaymentPopup.vue').default
);

Vue.component(
    'order-details',
    require('./components/OrderDetails.vue').default
);

Vue.component(
    'card-details',
    require('./components/CardDetails.vue').default
);

Vue.component(
    'card',
    require('./components/Card.vue').default
);

Vue.component(
    'card-topup',
    require('./components/Topup.vue').default
);

Vue.component(
    'transactions-table',
    require('./components/TransactionsTable.vue').default
);

Vue.filter('formatDate', function(value) {
    if (value) {
        return moment(value).format('DD/MM/YYYY hh:mm:ss');
    }
});

const router = new VueRouter({
    mode: 'history',
    base: '/sales/',
    routes: [
        {
            path: '/',
            name: 'home',
            component: Events
        },
        {
            path: '/hello',
            name: 'hello',
            component: Hello,
        },

        {
            path: '/events',
            name: 'events',
            component: Events,
        },

        {
            path: '/events/:id/menu',
            name: 'menu',
            component: Menu,
        },

        {
            path: '/events/:id/hq',
            name: 'hq',
            component: Headquarters,
        },

        {
            path: '/events/:id/sales',
            name: 'sales',
            component: Sales,
        },

        {
            path: '/events/:id/summary',
            name: 'summary',
            component: SalesSummary,
        },

        {
            path: '/events/:id/attendees',
            name: 'attendees',
            component: Attendees,
        },

        {
            path: '/events/:id/check-in',
            name: 'checkIn',
            component: CheckIn
        },

        {
            path: '/cards',
            name: 'cards',
            component: Cards,
        },

        {
            path: '/settings',
            name: 'settings',
            component: Settings
        },

        {
            path: '/transactions',
            name: 'transactions',
            component: Transactions
        },

        {
            path: '/tests/transactions',
            name: 'testTransactions',
            component: TestTransactions
        },

        {
            path: '/financial-overview',
            name: 'financialOverview',
            component: FinancialOverview
        }
    ],
});


/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

// Bootstrap card service
Vue.prototype.$settingService = new SettingService();
Vue.prototype.$organisationService = new OrganisationService();

Vue.prototype.$settingService.load()
    .then(
        function() {

            Vue.prototype.$cardService = new CardService(
                window.axios.create({
                    baseURL: '/api/v1',
                    json: true
                }),
                window.ORGANISATION_ID
            );

            Vue.prototype.$kioskModeService = new KioskService();

            // Only try to connect to the nfc reader if config variables are set.
            if (
                Vue.prototype.$settingService.nfcServer
            ) {
                Vue.prototype.$cardService.connect(
                    Vue.prototype.$settingService.nfcServer,
                    Vue.prototype.$settingService.nfcPassword
                );

                Vue.prototype.$organisationService.get(ORGANISATION_ID, { fields: '*,secret'})
                    .then(
                        (organisation) => {
                            Vue.prototype.$cardService.setPassword(organisation.secret);
                        }
                    );
            }

            // Payment service
            Vue.prototype.$paymentService = new PaymentService();
            if (Vue.prototype.$cardService) {
                Vue.prototype.$paymentService.setCardService(Vue.prototype.$cardService);
            }

            // and now boot the app
            const app = new Vue({
                el: '#app',
                components: { App },
                router,
                methods: {
                    refreshToken: function() {
                        window.location.reload();
                    }
                }
            });

        }.bind(this)
    );
