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

import {NfcReader} from "../../shared/js/nfccards/nfc/NfcReader";
import {SettingService} from "../../shared/js/services/SettingService";

const settingService = new SettingService();
settingService.load()
    .then(
        function() {

            if (!settingService.nfcServer) {
                alert('NFC server address not set.');
                return;
            }
            const nfcReader = new NfcReader(null, console);
            nfcReader.connect(settingService.nfcServer, settingService.nfcPassword, false);

            let knownUIds = {};

            nfcReader.on('card:connect', function(card) {

                if (typeof(knownUIds[card.getUid()]) !== 'undefined') {
                    return;
                }

                knownUIds[card.getUid()] = true;

                let html = '';
                html += '<div class="card">';
                html += '<div class="qr"><img src="/qr-generator/code?uid=' + card.getUid() + '" /></div>';
                html += '<div class="uid">' + card.getUid() + '</div>';
                html += '</div>';

                document.getElementById('cards').innerHTML += html;
            });
        }
    );

