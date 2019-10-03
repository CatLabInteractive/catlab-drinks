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
import {OfflineStore} from "../store/OfflineStore";
import {InvalidMessageException} from "../exceptions/InvalidMessageException";
import {CorruptedCard} from "../exceptions/CorruptedCard";
import {Logger} from "../tools/Logger";
import {NfcWriteException} from '../exceptions/NfcWriteException';

/**
 *
 */
export class NfcReader extends Eventable {

    private socket: any;

    private password: string = '';

    private currentCard: Card | null = null;

    constructor(
        private offlineStore: OfflineStore,
        private logger: Logger
    ) {
        super();
    }

    bin2string(array: any) {
        var result = "";
        for(var i = 0; i < array.length; ++i){
            result+= (String.fromCharCode(array[i]));
        }

        return result;
    }

    connect(url: string, password: string, handleHandshake = true) {
        this.socket = io(url);

        /**
         *
         */
        this.socket.on('connect', () => {
            this.handshake();
        });

        /**
         *
         */
        this.socket.on('nfc:card:connect', (data: any, resolve: any) => {

            const card = new Card(this, data.uid);
            if (handleHandshake) {

                const password = this.calculateCardPassword(data.uid);
                this.socket.emit('nfc:password', {
                    uid: data.uid,
                    password: password
                });

                this.currentCard = card;

            }

            this.trigger('card:connect', card);
            this.logger.log(card.getUid(), 'connected');
        });

        /**
         *
         */
        this.socket.on('nfc:card:disconnect', (data: any, resolve: any) => {
            this.currentCard = null;
            this.trigger('card:disconnect');

            this.logger.log(data.uid, 'disconnected');
        });

        /**
         *
         */
        this.socket.on('nfc:data', async (data: any, resolve: any) => {
            const uid = data.uid;
            if (this.currentCard === null || this.currentCard.getUid() !== uid) {
                this.logger.log(uid, 'Current card doesnt have the same uid as the data card');
                return;
            }

            this.logger.log(uid, 'data received', data);

            try {
                await this.setCardData(this.currentCard, data);

                // check if our last known transaction still matches
                const lastSeenSyncId = this.offlineStore.getLastKnownSyncId(this.currentCard.getUid());
                if (lastSeenSyncId > this.currentCard.transactionCount) {
                    this.currentCard.setCorrupted();
                    this.trigger('card:corrupted', this.currentCard);
                    return;
                }
                this.offlineStore.setLastKnownSyncId(this.currentCard.getUid(), this.currentCard.transactionCount);

            } catch (e) {
                if (e instanceof CorruptedCard) {
                    this.currentCard.setCorrupted();
                    this.trigger('card:corrupted', this.currentCard);
                } else {
                    throw e;
                }
            }

            this.trigger('card:loaded', this.currentCard);
        });

        this.socket.on('disconnect', () => {
            this.reconnect();
        });
    }

    /**
     * @param card
     * @param data
     */
    private async setCardData(card: Card, data: any) {

        if (typeof(data.ndef) !== 'undefined') {

            const bytes = this.base64ToByteArray(data.ndef);
            const ndefDecoded = ndef.decodeMessage(bytes);

            // if the ndef messsage could not be decoded, try to recover from internal state
            try {
                card.parseNdef(ndefDecoded);

                // Store the original state locally to be able to revert to it on write error
                await this.offlineStore.setCardState(card.getUid(), data.ndef);

            } catch (e) {
                if (e instanceof InvalidMessageException) {
                    await this.recoverInvalidContent(card);

                    // in case of failed recovery, immediately write the recovered content to the card
                    await this.write(card);

                } else {
                    throw e;
                }
            }
        } else if (data.data) {

            // is this a brand new card?
            const bytes = this.base64ToByteArray(data.data);

            if (bytes[0] === 0x00 && bytes[1] === 0x00 && bytes[2] === 0x00) {
                // this is a brand new card.
                this.logger.log(card.getUid(), 'New card detected, writing empty data.');
                await this.write(card);
                return;
            }

            // not a new card, try to recover the data from local data.
            await this.recoverInvalidContent(card);
        } else {
            await this.recoverInvalidContent(card);
        }
    }

    /**
     * @param message
     */
    private base64ToByteArray(message: string) {
        const decodedData = atob(message);

        let len = decodedData.length;
        let bytes = [];
        for (let i = 0; i < len; i++) {
            bytes[i] = decodedData.charCodeAt(i);
        }
        return bytes;
    }

    /**
     * @param card
     * @param throwException
     */
    public async recoverInvalidContent(card: Card, throwException = true) {

        this.logger.log(card.getUid(), 'recoving failed write');

        // look for existing message
        const data = await this.offlineStore.getCardState(card.getUid());

        if (data) {
            try {
                const ndefDecoded = ndef.decodeMessage(this.base64ToByteArray(data));
                card.parseNdef(ndefDecoded);

                this.logger.log(card.getUid(), 'succesfully recovered data');
            } catch (e) {
                if (e instanceof InvalidMessageException) {
                    throw new CorruptedCard("The data in memory is corrupted as well. This should never happen.");
                } else {
                    throw e;
                }
            }
        } else if (throwException) {
            this.logger.log(card.getUid(), "The data on the card is damaged and we could not recover it from memory.");
            throw new CorruptedCard("The data on the card is damaged and we could not recover it from memory.");
        }
    }

    /**
     * @param card
     */
    public async write(card: Card) {

        if (this.currentCard === null || this.currentCard.getUid() !== card.getUid()) {
            this.logger.log(card.getUid(), 'Current card doesnt have the same uid as the data card');
            return;
        }

        let message = card.getNdefMessages();

        let byteArray = ndef.encodeMessage(message);
        let base64 = btoa(this.bin2string(byteArray));

        // write some other data
        await new Promise(
            (resolve, reject) => {
                this.socket.emit('nfc:write', {
                    uid: card.getUid(),
                    ndef: base64
                }, (response: any) => {
                    if (response.success) {

                        // store the new state in localstorage
                        this.offlineStore.setCardState(card.getUid(), base64);

                        resolve();
                    } else {
                        reject(new NfcWriteException(response.error));
                    }
                });
            }
        );



    }

    /**
     * @param password
     */
    public setPassword(password: string) {
        this.password = password;
        return this;
    }

    /**
     * @param card
     * @param content
     */
    public hmac(card: Card, content: string) {
        return CryptoJS.HmacSHA256(content + card.getUid(), this.password);
    }

    /**
     *
     */
    private handshake() {

    }

    /**
     *
     */
    private reconnect() {

    }

    /**
     * @param uid
     */
    private calculateCardPassword(uid: string) {
        const password = CryptoJS.SHA256(uid + this.password);
        return password.toString(CryptoJS.enc.Hex).substr(0, 8);
    }

}
