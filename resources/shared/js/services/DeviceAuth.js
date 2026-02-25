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

/**
 * Checks whether the native DeviceService is available (Capacitor/Cordova app).
 * @returns {boolean}
 */
function hasNativeDeviceService() {
    return typeof(window.CATLAB_DRINKS_APP) !== 'undefined'
        && window.CATLAB_DRINKS_APP.DeviceService;
}

/**
 * Get the native DeviceService instance.
 * @returns {object|null}
 */
function getNativeDeviceService() {
    if (!hasNativeDeviceService()) {
        return null;
    }
    return new window.CATLAB_DRINKS_APP.DeviceService();
}

/**
 * Get stored auth data (apiUrl, accessToken, apiIdentifier).
 * Uses native DeviceService when available, otherwise falls back to localStorage.
 * @returns {Promise<{apiUrl: string, accessToken: string, apiIdentifier: string}|null>}
 */
export async function getAuthData() {
    const deviceService = getNativeDeviceService();
    if (deviceService) {
        const auth = await deviceService.getAuthData();
        if (auth && auth.apiUrl && auth.accessToken && auth.apiIdentifier) {
            return auth;
        }
        return null;
    }

    // Fallback to localStorage
    const apiIdentifier = window.localStorage.getItem('catlab_drinks_pos_api_identifier');
    if (!apiIdentifier) {
        return null;
    }

    const accessToken = window.localStorage.getItem('catlab_drinks_pos_access_token[' + apiIdentifier + ']');
    const apiUrl = window.localStorage.getItem('catlab_drinks_pos_api_url[' + apiIdentifier + ']');

    if (!accessToken || !apiUrl) {
        return null;
    }

    return { apiUrl, accessToken, apiIdentifier };
}

/**
 * Store auth data after successful pairing.
 * Uses native DeviceService when available, otherwise falls back to localStorage.
 * @param {string} apiUrl
 * @param {string} accessToken
 * @param {string} apiIdentifier
 * @returns {Promise<void>}
 */
export async function setAuthData(apiUrl, accessToken, apiIdentifier) {
    const deviceService = getNativeDeviceService();
    if (deviceService) {
        await deviceService.setAuthData({ apiUrl, accessToken, apiIdentifier });
        return;
    }

    // Fallback to localStorage
    window.localStorage.setItem('catlab_drinks_pos_api_identifier', apiIdentifier);
    window.localStorage.setItem('catlab_drinks_pos_api_url[' + apiIdentifier + ']', apiUrl);
    window.localStorage.setItem('catlab_drinks_pos_access_token[' + apiIdentifier + ']', accessToken);
}

/**
 * Clear all auth data (on logout or 401).
 * Uses native DeviceService when available, otherwise falls back to localStorage.
 * @returns {Promise<void>}
 */
export async function clearAuthData() {
    const deviceService = getNativeDeviceService();
    if (deviceService) {
        await deviceService.clearAuthData();
        return;
    }

    // Fallback to localStorage
    const apiIdentifier = window.localStorage.getItem('catlab_drinks_pos_api_identifier');
    window.localStorage.removeItem('catlab_drinks_pos_api_identifier');
    if (apiIdentifier) {
        window.localStorage.removeItem('catlab_drinks_pos_api_url[' + apiIdentifier + ']');
        window.localStorage.removeItem('catlab_drinks_pos_access_token[' + apiIdentifier + ']');
    }
}

/**
 * Get the device UUID.
 * Uses native DeviceService when available, otherwise falls back to localStorage.
 * @returns {Promise<string|null>}
 */
export async function getDeviceUuid() {
    const deviceService = getNativeDeviceService();
    if (deviceService) {
        return await deviceService.getDeviceUuid();
    }

    // Fallback to localStorage
    const deviceId = window.localStorage.getItem('catlab_drinks_device_pos_uid');
    return deviceId || null;
}

/**
 * Store the device UUID (only used in localStorage fallback mode; native UUID is read-only).
 * @param {string} deviceUid
 * @returns {Promise<void>}
 */
export async function setDeviceUuid(deviceUid) {
    if (hasNativeDeviceService()) {
        // Native device UUID is read-only, no-op
        return;
    }

    window.localStorage.setItem('catlab_drinks_device_pos_uid', deviceUid);
}
