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

import {AbstractService} from "./AbstractService";
import * as localForage from "localforage";

/**
 * This class creates a queue for all write actions and stores it in localstorage so that all
 * transactions will eventually be synced to the server.
 *
 * Create and update methods will be resolved immediately since no requests are made on call.
 */
export class AbstractOfflineQueue extends AbstractService {

	constructor(localStoragePrefix) {
		super();

		this.localForagePrefix = localStoragePrefix + '_';
		this.uploading = false;
	}

	startPeriodicUpload() {
		this.uploadQueue();
	}

	async create (data, parameters) {
		this.addQueueItem('create', null, data, parameters);
	}

	async uploadQueue() {

		this.uploading = true;

		// look for any items in the queue
		let pendingActions = await this.getPendingActions();

		let createOrders = pendingActions.filter((item) => {
			return item.method === 'create';
		});

		// upload createOrders in batches.
		while (createOrders.length > 0) {
			const batch = createOrders.splice(0, 10);
			await this.uploadBatch(batch);
		}

		this.uploading = false;

		this.timeout = setTimeout(
			() => {
				this.uploadQueue();
			},
			30000
		);
	}

	async uploadBatch(items) {

		const body = {
			items: []
		};

		items.forEach((item) => {
			body.items.push(item.data);
		});

		await this.execute('post', '/' + this.indexUrl, body);

		// also remove these entries from the queue
		items.forEach((item) => {
			localForage.removeItem(item.localStorageKey);
		});
	};

	/**
	 * Get all transactions that have not been synced to the online api
	 */
	async getPendingActions() {
		return await new Promise(
			(resolve, reject) => {
				const out = [];

				localForage.iterate((value, key, iterationNumber) => {
					if (this.keyStartsWith(key, '')) {
						const transaction = value;
						transaction.localStorageKey = key;
						out.push(transaction);
					}
				}).then(
					() => {
						resolve(out);
					}
				);
			}
		);
	}

	/**
	 * @param method
	 * @param id
	 * @param body
	 * @param parameters
	 * @returns {Promise<unknown>}
	 */
	async addQueueItem(method, id, body, parameters) {

		return new Promise(
			(resolve, reject) => {
				localForage.setItem(
					this.localForagePrefix + (new Date()).getTime(),
					{
						id: id,
						method: method,
						data: body,
						parameters: parameters
					},
					function(err, result) {
						if (err) {
							reject(err);
							return;
						}
						resolve();
					}
				);
			}
		);

	}

	/**
	 * @param key
	 * @param check
	 */
	keyStartsWith(key, check) {
		const fullCheck = this.localForagePrefix + check;
		return key.substr(0, fullCheck.length) === fullCheck
	}

	destroy() {
		if (this.interval) {
			clearTimeout(this.timeout);
		}

		// Submit all remaining items
		if (!this.uploading) {
			this.uploadQueue();
		}
	}

}
