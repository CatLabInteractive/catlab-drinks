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

import localForage from 'localforage';

const CACHE_PREFIX = 'api_cache_';

/**
 * Cache an API response for the given URL.
 * @param {string} url
 * @param {*} data - The response data to cache.
 * @returns {Promise<void>}
 */
export async function cacheResponse(url, data) {
    try {
        await localForage.setItem(CACHE_PREFIX + url, {
            data: data,
            timestamp: Date.now()
        });
    } catch (e) {
        console.warn('Failed to cache API response:', e);
    }
}

/**
 * Retrieve a cached API response for the given URL.
 * @param {string} url
 * @returns {Promise<*|null>} The cached response data, or null if not found.
 */
export async function getCachedResponse(url) {
    try {
        const cached = await localForage.getItem(CACHE_PREFIX + url);
        if (cached && cached.data) {
            return cached.data;
        }
    } catch (e) {
        console.warn('Failed to read cached API response:', e);
    }
    return null;
}

/**
 * Check if a request error is a network error (no response received).
 * @param {Error} error
 * @returns {boolean}
 */
export function isNetworkError(error) {
    return !error.response && (error.code === 'ERR_NETWORK' || error.message === 'Network Error' || (typeof navigator !== 'undefined' && !navigator.onLine));
}

/**
 * Install caching axios interceptors on a given axios instance.
 * Successful GET responses are cached; on network errors for GET requests,
 * the cached response is returned instead.
 *
 * @param {import('axios').AxiosInstance} axiosInstance
 * @param {import('./OfflineManager').OfflineManager} offlineManager
 */
export function installCacheInterceptors(axiosInstance, offlineManager) {
    // On successful response, cache GET requests and mark online
    axiosInstance.interceptors.response.use(
        response => {
            offlineManager.markOnline();
            if (response.config && response.config.method === 'get') {
                const url = response.config.url || '';
                cacheResponse(url, response.data);
            }
            return response;
        },
        async error => {
            if (isNetworkError(error)) {
                offlineManager.markOffline();

                // For GET requests, try to return cached data
                if (error.config && error.config.method === 'get') {
                    const url = error.config.url || '';
                    const cached = await getCachedResponse(url);
                    if (cached) {
                        console.info('[Offline] Serving cached response for:', url);
                        return { data: cached, status: 200, config: error.config, cached: true };
                    }
                }
            }
            return Promise.reject(error);
        }
    );
}
