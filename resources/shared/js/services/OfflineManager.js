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
 */
export class OfflineManager {

    constructor() {
        this._online = typeof navigator !== 'undefined' ? navigator.onLine : true;
        this._listeners = [];

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
     * Call when an API request succeeds to confirm connectivity.
     */
    markOnline() {
        this._setOnline(true);
    }

    /**
     * Call when an API request fails with a network error to flag loss of connectivity.
     */
    markOffline() {
        this._setOnline(false);
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

/**
 * Singleton instance of OfflineManager.
 * @type {OfflineManager|null}
 */
let _instance = null;

/**
 * Get the singleton OfflineManager instance.
 * @returns {OfflineManager}
 */
export function getOfflineManager() {
    if (!_instance) {
        _instance = new OfflineManager();
    }
    return _instance;
}
