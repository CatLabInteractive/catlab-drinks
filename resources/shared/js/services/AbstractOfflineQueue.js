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

		// When the device comes back online, trigger an immediate upload
		if (typeof window !== 'undefined' && window.OFFLINE_MANAGER) {
			this._offlineBinding = window.OFFLINE_MANAGER.on((online) => {
				if (online && !this.uploading) {
					clearTimeout(this.timeout);
					this.uploadQueue();
				}
			});
		}
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

	/**
	 * Check if a localForage value looks like a queue item.
	 * Queue items have method (e.g. 'create'), data, and id properties.
	 * @param {*} value
	 * @returns {boolean}
	 */
	static isQueueItem(value) {
		return value && typeof value === 'object' && typeof value.method === 'string' && 'data' in value && 'id' in value;
	}

	/**
	 * Get the count of all pending items across all AbstractOfflineQueue instances
	 * by scanning localForage for items with a queue item structure.
	 * @returns {Promise<number>}
	 */
	static async getAllPendingCount() {
		return new Promise((resolve) => {
			let count = 0;
			localForage.iterate((value, key) => {
				if (AbstractOfflineQueue.isQueueItem(value)) {
					count++;
				}
			}).then(() => resolve(count));
		});
	}

	/**
	 * Upload all pending items from all AbstractOfflineQueue instances.
	 * Groups items by their key prefix and uploads each group.
	 * Currently only supports OrderService queues with the "event_<id>" prefix pattern.
	 * @returns {Promise<void>}
	 */
	static async uploadAllPending() {
		const itemsByPrefix = {};

		await localForage.iterate((value, key) => {
			if (AbstractOfflineQueue.isQueueItem(value)) {
				// Extract the event prefix from the key (e.g., "event_123_<timestamp>")
				const lastUnderscore = key.lastIndexOf('_');
				if (lastUnderscore > 0) {
					const prefix = key.substring(0, lastUnderscore);
					if (!itemsByPrefix[prefix]) {
						itemsByPrefix[prefix] = [];
					}
					value.localStorageKey = key;
					itemsByPrefix[prefix].push(value);
				}
			}
		});

		const errors = [];
		for (const prefix of Object.keys(itemsByPrefix)) {
			try {
				const items = itemsByPrefix[prefix];
				// Extract event ID from the prefix pattern "event_<id>"
				const match = prefix.match(/^event_(\d+)$/);
				if (match) {
					const { OrderService } = await import('./OrderService');
					const service = new OrderService(match[1]);
					const createOrders = items.filter((item) => item.method === 'create');
					while (createOrders.length > 0) {
						const batch = createOrders.splice(0, 10);
						await service.uploadBatch(batch);
					}
				}
			} catch (e) {
				errors.push(e);
			}
		}

		if (errors.length > 0) {
			throw errors[0];
		}
	}

	destroy() {
		if (this.timeout) {
			clearTimeout(this.timeout);
		}

		if (this._offlineBinding) {
			this._offlineBinding.unbind();
			this._offlineBinding = null;
		}

		// Submit all remaining items
		if (!this.uploading) {
			this.uploadQueue();
		}
	}

}
