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
import {CorruptedCardException} from "../exceptions/CorruptedCardException";
import {Logger} from "../tools/Logger";
import {NfcWriteException} from '../exceptions/NfcWriteException';

/**
 *
 */
export abstract class NfcReader extends Eventable {

    protected password: string = '';

    protected topupDomain: string = 'd.ctlb.eu';

    protected currentCard: Card | null = null;

    protected executeHandshake: boolean;

    protected nfcReaderPassword: string;

    constructor(
        protected offlineStore: OfflineStore,
        protected logger: Logger
    ) {
        super();
        this.executeHandshake = false;
        this.nfcReaderPassword = '';
    }

    connect(url: string, password: string, handleHandshake = true) {

    }

    /**
     * @param message
     */
    protected base64ToByteArray(message: string) {
		try {
        	const decodedData = atob(message);

			let len = decodedData.length;
			let bytes: any = [];
			for (let i = 0; i < len; i++) {
				bytes[i] = decodedData.charCodeAt(i);
			}
			return bytes;

		} catch (e) {
			console.error(e);
			return null;
		}
    }

    protected bin2string(array: any) {
        var result = "";
        for(var i = 0; i < array.length; ++i){
            result+= (String.fromCharCode(array[i]));
        }

        return result;
    }

    protected bin2hex(array: any): string {
        return CryptoJS.enc.Hex.stringify(array);
    }

    /**
     * @param card
     * @param throwException
     */
    public async recoverInvalidContent(card: Card, throwException = true) {

        this.logger.log(card.getUid(), 'recovering failed write');

        // look for existing message
        const data = await this.offlineStore.getCardState(card.getUid());

        if (data) {
            try {
                const ndefDecoded = ndef.decodeMessage(this.base64ToByteArray(data));
                card.parseNdef(ndefDecoded);

                this.logger.log(card.getUid(), 'successfully recovered data');

				return true;

            } catch (e) {
                if (e instanceof InvalidMessageException) {
                    throw new CorruptedCardException("The data in memory is corrupted as well. This should never happen.");
                } else {
                    throw e;
                }
            }
        } else if (throwException) {
            this.logger.log(card.getUid(), "The data on the card is damaged and we could not recover it from memory.");
            throw new CorruptedCardException("The data on the card is damaged and we could not recover it from memory.");
        }

		this.logger.log(card.getUid(), "The data on the card is damaged and we could not recover it from memory.");
		return false;
    }

    /**
     * @param card
     */
    public abstract write(card: Card): Promise<void>;

    /**
     * @param password
     */
    public setPassword(password: string) {
        this.password = password;
        return this;
    }

    /**
     * @param domain
     */
    public setTopupDomain(domain: string) {
        this.topupDomain = domain;
        return this;
    }

    /**
     * @return string
     */
    public getTopupDomain(): string {
        return this.topupDomain;
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
    protected reconnect() {

    }

    /**
     * @param uid
     */
    protected calculateCardPassword(uid: string) {
        const password = CryptoJS.SHA256(uid + this.password);
        return password.toString(CryptoJS.enc.Hex).substr(0, 8);
    }

}
