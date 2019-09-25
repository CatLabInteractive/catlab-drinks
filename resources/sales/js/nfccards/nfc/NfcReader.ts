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

import * as io from 'socket.io-client';
import * as CryptoJS from 'crypto-js';

// @ts-ignore
import * as ndef from 'ndef';
import {Eventable} from "../../utils/Eventable";
import {Card} from "../models/Card";

/**
 *
 */
export class NfcReader extends Eventable {

    private socket: any;

    private password: string = '';

    private currentCard: Card | null = null;

    bin2string(array: any) {
        var result = "";
        for(var i = 0; i < array.length; ++i){
            result+= (String.fromCharCode(array[i]));
        }

        return result;
    }

    connect(url: string) {
        this.socket = io(url);

        this.socket.on('connect', () => {

            this.handshake();

        });

        this.socket.on('nfc:card:connect', (data: any, resolve: any) => {
            const password = this.calculateCardPassword(data.uid);
            this.socket.emit('nfc:password', {
                uid: data.uid,
                password: password
            });

            const card = new Card(this, data.uid);
            this.currentCard = card;
            this.trigger('card:connect', card);
        });

        this.socket.on('nfc:card:disconnect', (data: any, resolve: any) => {
            this.currentCard = null;
            this.trigger('card:disconnect');
        });

        this.socket.on('nfc:data', (data: any, resolve: any) => {

            const uid = data.uid;
            if (this.currentCard === null || this.currentCard.getUid() !== uid) {
                console.log('Current card doesnt have the same uid as the data card');
                return;
            }

            if (typeof(data.ndef) !== 'undefined') {
                const decodedData = atob(data.ndef);

                let len = decodedData.length;
                let bytes = [];
                for (let i = 0; i < len; i++) {
                    bytes[i] = decodedData.charCodeAt(i);
                }

                const ndefDecoded = ndef.decodeMessage(bytes);
                this.currentCard.parseNdef(ndefDecoded);
                //console.log('ndef data received: ', ndefDecoded[0].value);
            }

            // random change
            //this.currentCard.balance += ((Math.random() / 0.5) - 1) * 1000

            let message = this.currentCard.getNdefMessages();

            let byteArray = ndef.encodeMessage(message);
            const base64 = btoa(this.bin2string(byteArray));

            // write some other data
            this.socket.emit('nfc:write', {
                uid: data.uid,
                ndef: base64
            }, (response: any) => {
                console.log(response);
            });


        });

        this.socket.on('disconnect', () => {
            this.reconnect();
        });
    }

    public setPassword(password: string) {
        this.password = password;
        return this;
    }

    public hmac(card: Card, content: string) {
        return CryptoJS.HmacSHA256(content + card.getUid(), this.password);
    }

    private handshake() {

    }

    private reconnect() {

    }

    private calculateCardPassword(uid: string) {
        const password = CryptoJS.SHA256(uid + this.password);
        return password.toString(CryptoJS.enc.Hex).substr(0, 8);
    }

}
