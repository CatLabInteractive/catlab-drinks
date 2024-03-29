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

import $ from 'jquery';

export class AbstractService {

    constructor() {
        this.entityUrl = '';
        this.indexUrl = '';

        this.client = window.axios.create({
            baseURL: '/api/v1',
            json: true
        });

        // Add authentication interceptor
        this.client.interceptors.response.use(
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
                    return false;
                }

                // Handle Forbidden
                if (status === 403) {
                    console.log({403:error});
                    alert(error.message);
                    window.location = window.location.origin;
                    return false;
                }

                if (status === 422) {
                    console.log({403:error});

                    var issues = [];
                    for (var k in error.response.data.error.issues) {
                        if (error.response.data.error.issues.hasOwnProperty(k)) {
                            issues.push(error.response.data.error.issues[k][0]);
                        }
                    }

                    alert(error.response.data.error.message + ': \n' + issues.join(', \n'));
                    return false;
                }

                return Promise.reject(error)
            }
        );
    }

    /**
     * @param method
     * @param resource
     * @param data
     * @returns {Promise<void>}
     */
    async execute(method, resource, data = {}) {
        return this.client({
            method: method,
            url: resource,
            data: data
        }).then(
            (response) => {
                return response.data;
            }
        )
    }

    async index (parameters) {
        if (typeof(parameters) === 'undefined') {
            parameters = {};
        }

        parameters.records = 1000;

        return this.execute('get', this.indexUrl + "?" + $.param(parameters))
    }

    async create (data, parameters) {

        if (typeof(parameters) === 'undefined') {
            parameters = {};
        }

        return this.execute('post', '/' + this.indexUrl + "?" + $.param(parameters), data);
    }

    async get (id, parameters) {
        
        if (typeof(parameters) === 'undefined') {
            parameters = {};
        }

        return this.execute('get', '/' + this.entityUrl + '/' + id + "?" + $.param(parameters));
    }

    async update (id, data, parameters) {

        if (typeof(parameters) === 'undefined') {
            parameters = {};
        }

        return this.execute('put', '/' + this.entityUrl + '/' + id + "?" + $.param(parameters), data);
    }

    async delete (id, parameters) {

        if (typeof(parameters) === 'undefined') {
            parameters = {};
        }

        return this.execute('delete', '/' + this.entityUrl + '/' + id + "?" + $.param(parameters));
    }

    destroy() {

    }
}
