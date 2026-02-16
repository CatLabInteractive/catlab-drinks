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
import localForage from "localforage";

export class SettingService {

	terminalName = 'bar';
	nfcServer = null;
	nfcPassword = null;

	allowLiveOrders = true;
	allowRemoteOrders = true;

    constructor() {



    }

    load() {
        return new Promise(
            function(resolve, reject) {

                localForage.getItem('settings', function(err, settings) {

                    if (!settings) {
                        settings = {};
                    }

                    this.terminalName = settings.terminalName || 'bar';
                    this.nfcServer = settings.nfcServer || null;
                    this.nfcPassword = settings.nfcPassword || null;

					if (typeof(settings.allowLiveOrders) !== 'undefined') {
						this.allowLiveOrders = settings.allowLiveOrders ? true : false;
					}

					if (typeof(settings.allowRemoteOrders) !== 'undefined') {
						this.allowRemoteOrders = settings.allowRemoteOrders ? true : false;
					} else if (typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.nfc) {
						this.allowRemoteOrders = false;
					}

                    resolve();

                }.bind(this));

            }.bind(this)
        );
    }

    save() {
        return new Promise(
            function(resolve, reject) {
                localForage.setItem('settings', {
                    terminalName : this.terminalName,
                    nfcServer: this.nfcServer,
                    nfcPassword: this.nfcPassword,

					allowLiveOrders: this.allowLiveOrders,
					allowRemoteOrders: this.allowRemoteOrders
                }, function() {
                    resolve();
                });
            }.bind(this)
        );
    }

}
