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
import {installCacheInterceptors} from './ApiCacheService';

/**
 * Extract a message and a list of issues from an API error response.
 * The API returns errors in several shapes:
 * - CatLab: { error: { message, issues: { field: [ message ] } } }
 * - JSON:API (Charon error handler): { errors: [ { title, detail } ] }
 * - Laravel: { message, errors: { field: [ message ] } }
 * @param error
 * @returns {{message: (string|null), issues: string[]}}
 */
function parseApiError(error) {
	const data = error && error.response ? error.response.data : null;
	const firstOfEach = (map) => Object.values(map || {})
		.map((value) => Array.isArray(value) ? value[0] : value)
		.filter((value) => typeof value === 'string');

	if (data && typeof data === 'object') {
		if (data.error && typeof data.error === 'object') {
			return { message: data.error.message || null, issues: firstOfEach(data.error.issues) };
		}

		if (Array.isArray(data.errors)) {
			return {
				message: data.errors[0] && data.errors[0].title ? data.errors[0].title : null,
				issues: data.errors.map((e) => e && e.detail).filter((detail) => typeof detail === 'string')
			};
		}

		if (typeof data.message === 'string') {
			return { message: data.message, issues: firstOfEach(data.errors) };
		}
	}

	return { message: null, issues: [] };
}

/**
 * Human readable message for a failed API request, or null if the
 * response does not contain one.
 * @param error
 * @returns {string|null}
 */
export function getApiErrorMessage(error) {
	const parsed = parseApiError(error);
	if (parsed.issues.length > 0) {
		return parsed.issues.join(', \n');
	}
	return parsed.message;
}

export class AbstractService {

	constructor() {
		this.entityUrl = '';
		this.indexUrl = '';

		this.client = window.axios.create({
			baseURL: CATLAB_DRINKS_CONFIG.API_PATH,
			json: true
		});

		// Install caching interceptors if offline manager is available (POS app only)
		if (typeof window !== 'undefined' && window.OFFLINE_MANAGER) {
			installCacheInterceptors(this.client, window.OFFLINE_MANAGER);
		}

		// Add authentication interceptor
		this.client.interceptors.response.use(
			response => response,
			error => {

				// Network errors (no response) — reject without alerts
				if (!error.response) {
					return Promise.reject(error);
				}

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

					// The page is navigating away; keep the request pending so
					// callers never see a bogus resolved value.
					return new Promise(() => {});
				}

				// Handle Forbidden
				if (status === 403) {
					console.log({403:error});
					alert(error.message);
					window.location = window.location.origin;
					return new Promise(() => {});
				}

				if (status === 422) {
					console.log({422:error});

					const parsed = parseApiError(error);
					const message = parsed.message || error.message;

					if (parsed.issues.length > 0) {
						alert(message + ': \n' + parsed.issues.join(', \n'));
					} else {
						alert(message);
					}
					return Promise.reject(error);
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

		const response = await this.execute('get', this.indexUrl + "?" + $.param(parameters))

		return {
			items: response.items.map((item) => {
				return this.mapFromServerData(item);
			})
		};
	}

	async create (data, parameters) {

		if (typeof(parameters) === 'undefined') {
			parameters = {};
		}

		return this.execute(
			'post',
			'/' + this.indexUrl + "?" + $.param(parameters),
			this.mapToServerData(data)
		);
	}

	async get (id, parameters) {

		if (typeof(parameters) === 'undefined') {
			parameters = {};
		}

		const response = await this.execute('get', '/' + this.entityUrl + '/' + id + "?" + $.param(parameters));

		return this.mapFromServerData(response);
	}

	async update (id, data, parameters) {

		if (typeof(parameters) === 'undefined') {
			parameters = {};
		}

		return this.execute(
			'put',
			'/' + this.entityUrl + '/' + id + "?" + $.param(parameters),
			this.mapToServerData(data)
		);
	}

	async delete (id, parameters) {

		if (typeof(parameters) === 'undefined') {
			parameters = {};
		}

		return this.execute('delete', '/' + this.entityUrl + '/' + id + "?" + $.param(parameters));
	}

	mapToServerData(data) {
		return data;
	}

	mapFromServerData(data) {
		return data;
	}

	destroy() {

	}
}
