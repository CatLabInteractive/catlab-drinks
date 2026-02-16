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
    window.CATLAB_DRINKS_CONFIG.ROUTER_BASE = '/pos/';
}

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

            // Clear all POS session data
            const apiIdentifier = window.localStorage.getItem('calab_drinks_pos_api_identifier');
            window.localStorage.removeItem('catlab_drinks_device_pos_uid');
            window.localStorage.removeItem('calab_drinks_pos_api_identifier');
            if (apiIdentifier) {
                window.localStorage.removeItem('catlab_drinks_pos_api_url[' + apiIdentifier + ']');
                window.localStorage.removeItem('catlab_drinks_pos_access_token[' + apiIdentifier + ']');
            }

            alert('Session expired. Please re-authenticate your device.');
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