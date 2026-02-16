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

import Vue, { createApp } from "vue";
import { createRouter, createWebHistory } from 'vue-router'
import BootstrapVue from "bootstrap-vue";
import moment from 'moment'
import AirbrakeClient from 'airbrake-js';

import {SettingService} from "../../shared/js/services/SettingService";
import {OrganisationService} from "../../shared/js/services/OrganisationService";

import App from './views/App'
import Hello from '../../shared/js/views/Hello'
import Events from './views/Events.vue'
import Sales from "../../shared/js/views/Sales";
import SalesSummary from "../../shared/js/views/SalesSummary";
import Cards from "../../shared/js/views/Cards";
import Settings from "../../shared/js/views/Settings";
import Transactions from "../../shared/js/views/Transactions";
import TestTransactions from "../../shared/js/views/TestTransactions";
import FinancialOverview from "../../shared/js/views/FinancialOverview";
import Attendees from "../../shared/js/views/Attendees";
import CheckIn from "../../shared/js/views/CheckIn";
import SalesSummaryNames from "../../shared/js/views/SalesSummaryNames";
import Menu from "./views/Menu.vue";
import Devices from "./views/Devices";
import OrganisationSettings from "./views/OrganisationSettings";

function launch() {

	if (typeof (AIRBRAKE_CONFIG) !== 'undefined' && AIRBRAKE_CONFIG !== null) {
		var airbrake = new AirbrakeClient(AIRBRAKE_CONFIG);
		Vue.config.errorHandler = function (err, vm, info) {
			airbrake.notify({
				error: err,
				params: {info: info}
			});
		};
	}

	Vue.use(BootstrapVue);

	const router = createRouter({
		history: createWebHistory(window.CATLAB_DRINKS_CONFIG.ROUTER_BASE),
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
				path: '/events/:id/summary-names',
				name: 'summary-names',
				component: SalesSummaryNames,
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
			},

			{
				path: '/devices',
				name: 'devices',
				component: Devices
			},

			{
				path: '/organisation-settings',
				name: 'organisationSettings',
				component: OrganisationSettings
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
			() => {
				return axios.get('/api/v1/users/me')
					.then(response => {
						window.ORGANISATION_ID = response.data.organisations.items[0].id;
					});
			}
		)
		.then(
			function () {

				// and now boot the app
				const app = createApp({
					components: {App},
					methods: {
						refreshToken: function () {
							window.location.reload();
						}
					}
				});

				app.config.globalProperties.$filters = {
					formatDate(value) {
						if (value) {
							return moment(value).format('DD/MM/YYYY HH:mm:ss');
						}
					}
				  }

				app.use(router);
				app.mount('#app');

				console.log('app mounted');

			}.bind(this)
		);
}


window.initializeAccessToken()
	.then(() => {
		launch();

	});
