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
 * OfflineManager tracks the online/offline connectivity state of the POS app.
 * It uses navigator.onLine for initial state and listens for browser online/offline events.
 * API request success/failure also updates the connectivity state.
 *
 * Usage: instantiate once and place on Vue.prototype.$offlineManager (see app.js).
 */
export class OfflineManager {

    constructor() {
        this._online = typeof navigator !== 'undefined' ? navigator.onLine : true;
        this._listeners = [];
        this._lastSyncTime = null;

        // Track recent API results for properlyOnline detection.
        // Each entry is true (success) or false (failure).
        this._recentResults = [];
        this._properlyOnlineThreshold = 3;

        if (typeof window !== 'undefined') {
            window.addEventListener('online', () => this._setOnline(true));
            window.addEventListener('offline', () => this._setOnline(false));
        }
    }

    /**
     * @returns {boolean} True if the device appears to be online.
     */
    isOnline() {
        return this._online;
    }

    /**
     * Returns true only when the device has had several consecutive successful
     * API requests, indicating a stable connection. Use this for latency-sensitive
     * operations (e.g. NFC card sync) where a flaky connection would cause delays.
     * @returns {boolean}
     */
    isProperlyOnline() {
        if (!this._online) return false;
        if (this._recentResults.length < this._properlyOnlineThreshold) return false;
        const recent = this._recentResults.slice(-this._properlyOnlineThreshold);
        return recent.every(r => r === true);
    }

    /**
     * Call when an API request succeeds to confirm connectivity.
     */
    markOnline() {
        this._lastSyncTime = new Date();
        this._recentResults.push(true);
        if (this._recentResults.length > 10) this._recentResults.shift();
        this._setOnline(true);
    }

    /**
     * Call when an API request fails with a network error to flag loss of connectivity.
     */
    markOffline() {
        this._recentResults.push(false);
        if (this._recentResults.length > 10) this._recentResults.shift();
        this._setOnline(false);
    }

    /**
     * Get the timestamp of the last successful API request.
     * @returns {Date|null}
     */
    getLastSyncTime() {
        return this._lastSyncTime;
    }

    /**
     * Register a listener that is called when online status changes.
     * @param {function(boolean): void} listener
     * @returns {{ unbind: function(): void }}
     */
    on(listener) {
        this._listeners.push(listener);
        return {
            unbind: () => {
                this._listeners = this._listeners.filter(l => l !== listener);
            }
        };
    }

    /**
     * @private
     */
    _setOnline(value) {
        const changed = this._online !== value;
        this._online = value;
        if (changed) {
            this._listeners.forEach(l => l(value));
        }
    }
}
