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


// @ts-ignore
import * as ndef from 'ndef';
import {OfflineStore} from "../store/OfflineStore";
import {Logger} from "../tools/Logger";
import {NfcReader} from "./NfcReader";
// @ts-ignore
import io from "socket.io-client";
import {Card} from "../models/Card";
import {CorruptedCardException} from "../exceptions/CorruptedCardException";
import {InvalidMessageException} from "../exceptions/InvalidMessageException";
import {NfcWriteException} from "../exceptions/NfcWriteException";

/**
 *
 */
export class RemoteNfcReader extends NfcReader {

    private socket: any;

    constructor(
        offlineStore: OfflineStore,
        logger: Logger
    ) {
        super(offlineStore, logger);
    }

    connect(url: string, password: string, handleHandshake = true) {
        this.socket = io(url);
        this.executeHandshake = handleHandshake;

        this.nfcReaderPassword = password;

        /**
         *
         */
        this.socket.on('nfc:card:connect', (data: any, resolve: any) => {

            const card = new Card(this, data.uid);
            card.setTopupDomain(this.topupDomain);
            if (this.executeHandshake) {

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
            } catch (e) {
                if (e instanceof CorruptedCardException) {
                    this.currentCard.setCorrupted();
                    this.trigger('card:corrupted', this.currentCard);
                } else {
                    throw e;
                }
            }

            this.logger.log(this.currentCard.getUid(), 'Card finished loading');
            this.trigger('card:loaded', this.currentCard);
        });

        this.socket.on('connect', async () => {
            await this.handshake();
        });

        this.socket.on('disconnect', () => {
            this.trigger('connection:change', false);
            this.reconnect();
        });

        this.socket.on('reconnect', async () => {
            await this.handshake();
        });
    }

    public async write(card: Card): Promise<void> {
        if (this.currentCard === null || this.currentCard.getUid() !== card.getUid()) {
            this.logger.log(card.getUid(), 'Current card doesnt have the same uid as the data card');
            return;
        }

        let messages = card.getNdefMessages();
		console.log('WRITING NDEF MESSAGES', messages);

        let byteArray = ndef.encodeMessage(messages);


        // write some other data
        return new Promise(
            (resolve, reject) => {

                let base64 = btoa(this.bin2string(byteArray));
                this.socket.emit('nfc:write', {
                    uid: card.getUid(),
                    ndef: base64
                }, (response: any) => {
                    if (response.success) {

                        // store the new state in localstorage
                        this.offlineStore.setCardState(card.getUid(), base64);

                        resolve();
                    } else {
                        console.log(response.error.error.name);
                        reject(new NfcWriteException(response.error.error.name));
                    }
                });
            }
        );
    }

    private async handshake()
    {
        this.socket.emit('hello', { password: this.nfcReaderPassword }, (response: any) => {
            console.log('Response from nfc reader hello: ', response);
            if (response.success) {
                this.trigger('connection:change', true);
            }
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
                this.logger.log(card.getUid(), 'NDEF messages parsed', ndefDecoded);

                this.logger.log(card.getUid(), 'Setting card state in offline store');
                await this.offlineStore.setCardState(card.getUid(), data.ndef);
                this.logger.log(card.getUid(), 'Done setting card state in offline store');

            } catch (e) {
                this.logger.log(card.getUid(), 'Error! Failed parsing ndef / setting offline store', e);
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
            await this.write(card);
        } else {
            await this.recoverInvalidContent(card);
            await this.write(card);
        }
    }
}
