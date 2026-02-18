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

import {CardService} from "../../shared/js/nfccards/CardService";
import {SettingService} from "../../shared/js/services/SettingService";
import {PaymentService} from "../../shared/js/services/PaymentService";
import {OrganisationService} from "../../shared/js/services/OrganisationService";
import {KioskService} from "../../shared/js/services/KioskService";

import App from './views/App'
import Hello from '../../shared/js/views/Hello'
import Events from './views/Events.vue'
import Headquarters from "./views/Headquarters.vue";
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
import Relax from "../../shared/js/components/Relax";


import Authenticate from "./views/Authenticate";
import { getAuthData } from "../../shared/js/services/DeviceAuth";

async function launch() {

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

	// Check if we have all required config.
	const authData = await getAuthData();
	if (!authData) {

		// Authenticate component.
		const app = createApp({
			components: {
				App: Authenticate
			}
		});

		app.mount('#app');
		return;

	}

	// Set the API URL.
	window.CATLAB_DRINKS_CONFIG.API = authData.apiUrl;
	window.CATLAB_DRINKS_CONFIG.API_PATH = CATLAB_DRINKS_CONFIG.API + '/pos-api/v1'

	window.axios.defaults.baseURL = CATLAB_DRINKS_CONFIG.API;
	window.axios.defaults.headers.common['Authorization'] = 'Bearer ' + authData.accessToken;

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
				return axios.get('/pos-api/v1/devices/current')
					.then(response => {
						window.ORGANISATION_ID = response.data.organisation.id;
						window.DEVICE_SECRET = response.data.secret_key;

						// Set device license if LicenseService is available
						if (response.data.license_key && typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.LicenseService) {
							try {
								const licenseService = new window.CATLAB_DRINKS_APP.LicenseService();
								licenseService.setLicense(response.data.license_key);
							} catch (e) {
								console.error('Failed to set device license:', e);
							}
						}
					});
			}
		)
		.then(
			function () {

				Vue.prototype.$cardService = new CardService(
					window.axios.create({
						baseURL: CATLAB_DRINKS_CONFIG.API + '/pos-api/v1',
						json: true
					}),
					window.ORGANISATION_ID
				);

				Vue.prototype.$kioskModeService = new KioskService();

				// Only try to connect to the nfc reader if config variables are set.
				if (
					Vue.prototype.$settingService.nfcServer ||
					(typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.nfc)
				) {
					try {

						Vue.prototype.$cardService.connect(
							Vue.prototype.$settingService.nfcServer,
							Vue.prototype.$settingService.nfcPassword
						);

						Vue.prototype.$organisationService.get(ORGANISATION_ID, {fields: '*,secret,topup_domain'})
							.then(
								(organisation) => {
									// WARNING: This MUST be the organisation secret, NOT the device secret.
									// Otherwise NFC cards will only be valid on the POS where they were registered
									Vue.prototype.$cardService.setPassword(organisation.secret);

									// Set the topup domain for NFC card URLs
									if (organisation.topup_domain) {
										Vue.prototype.$cardService.setTopupDomain(organisation.topup_domain);
									}
								}
							);

					} catch (e) {
						console.error('Error connecting to card service: ' + e);
						console.log(e.stack);
					}
				}

				// Payment service
				Vue.prototype.$paymentService = new PaymentService();
				if (Vue.prototype.$cardService) {
					Vue.prototype.$paymentService.setCardService(Vue.prototype.$cardService);
				}

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

			}.bind(this)
		);
}

launch();
