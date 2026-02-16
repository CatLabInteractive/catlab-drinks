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

window._ = require('lodash');

/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

try {
    window.Popper = require('popper.js').default;
} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (typeof(CATLAB_DRINKS_CONFIG) === 'undefined') {
    window.CATLAB_DRINKS_CONFIG = {};
}

if (typeof(window.CATLAB_DRINKS_CONFIG.ROUTER_MODE) === 'undefined') {
    window.CATLAB_DRINKS_CONFIG.ROUTER_MODE = 'history';
}

if (typeof(window.CATLAB_DRINKS_CONFIG.ROUTER_BASE) === 'undefined') {
    window.CATLAB_DRINKS_CONFIG.ROUTER_BASE = '/manage/';
}

if (typeof(window.CATLAB_DRINKS_CONFIG.API) === 'undefined') {
    window.CATLAB_DRINKS_CONFIG.API = '';
}

window.CATLAB_DRINKS_CONFIG.API_PATH = CATLAB_DRINKS_CONFIG.API + '/api/v1'

const accessToken = null;

if (typeof(window.requestAccessToken) === 'undefined') {
	window.requestAccessToken = function() {
		return new Promise((resolve, reject) => {

			const hash = window.location.hash;
			if (hash) {
				const parts = hash.split('&');
				for (let i = 0; i < parts.length; i++) {
					const part = parts[i];
					const subparts = part.split('=');
					if (subparts.length === 2 && subparts[0] === '#access_token') {
						localStorage.setItem('access_token', subparts[1]);
						window.location.hash = '/';

						resolve(localStorage.getItem('access_token'));
						return;
					}
				}
			}
			window.location = CATLAB_DRINKS_CONFIG.API + '/oauth/authorize?client_id=' + CATLAB_DRINKS_CONFIG.CLIENT_ID + '&redirect_uri=' + window.location.protocol + '//' + window.location.host + '/&response_type=token'

		});
	}
}

window.initializeAccessToken = function() {
	return new Promise((resolve, reject) => {

		let token = document.head.querySelector('meta[name="csrf-token"]');
		if (token) {
			window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
			resolve();
		} else if (accessToken) {
			window.axios.defaults.headers.common['Authorization'] = 'Bearer ' + accessToken;
			resolve();
		} else {
			// handle authentication
			if (localStorage.getItem('access_token')) {
				window.axios.defaults.headers.common['Authorization'] = 'Bearer ' + localStorage.getItem('access_token');
				resolve();
			} else {
				window.requestAccessToken()
					.then(function(token) {
						localStorage.setItem('access_token', token);

						window.axios.defaults.headers.common['Authorization'] = 'Bearer ' + localStorage.getItem('access_token');
						resolve();
					});
			}
		}

	});
}

window.axios.defaults.baseURL = CATLAB_DRINKS_CONFIG.API;

// Add authentication interceptor
window.axios.interceptors.response.use(
    response => response,
    error => {

        const status = error.response.status;

        // Show the user a 500 error
        if (status >= 500) {
            console.log({500:error});
            alert(error.message);
        }

        // Handle Session Timeouts
        if (status === 401) {
            console.log({401:error});
            window.location.reload();
        }

        // Handle Forbidden
        if (status === 403) {
            console.log({403:error});
            alert(error.message);
        }

        // Handle Forbidden
        if (status === 422) {
            alert(error.message);
        }

        return Promise.reject(error)
    }
);
