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
import * as CryptoJS from 'crypto-js';
import {NfcReader} from "../nfc/NfcReader";
import {InvalidMessageException} from "../exceptions/InvalidMessageException";
import {SignatureMismatchException} from "../exceptions/SignatureMismatchException";
import {Eventable} from "../../utils/Eventable";
import {VisibleAmount} from "../tools/VisibleAmount";
import {CardValidationException} from "../exceptions/CardValidationException";

/**
 *
 */
export class Card extends Eventable {

    public id: number | null = null;

    public balance: number = 0;

    public transactionCount: number = 0;

    public loaded = false;

    /**
     * 'ready' is service specific property that notes
     * that this card is prepared and ready for use
     * (slightly different from 'loaded' which just means that the card data is fetched from the card)
     */
    public ready = false;

    public previousTransactions = [
        0,
        0,
        0,
        0,
        0
    ];

    public lastTransaction: Date = new Date();

    public orderTokenAliases: string[] = [];

    /**
     * Discount percentage (min 0 max 100)
     */
    public discountPercentage = 0;

    private corrupted: boolean;

    /**
     * @param nfcReader
     * @param uid
     */
    constructor(
        private nfcReader: NfcReader,
        private uid: string
    ) {
        super();
        this.corrupted = false;
    }

    /**
     *
     */
    public getUid() {
        return this.uid;
    }

    /**
     * Parse the ndef messages
     * @param ndefMessages
     */
    public parseNdef(ndefMessages: any) {

        if (ndefMessages.length !== 2) {
            throw new InvalidMessageException('NDEF messages length is ' + ndefMessages.length);
        }

        // the second message should contain our external data
        const ourData = ndefMessages[1].payload;
        this.parsePayload(ourData);
        this.loaded = true;
    }

    /**
     * Get the messages that need to be written.
     */
    public getNdefMessages() {
        const out = [];

        // first, the topup url.
        out.push(ndef.uriRecord("http://d.ctlb.eu/" + this.uid));

        // next, our own internal (and signed) state
        const signedData = this.getSignedData();
        out.push(ndef.record(ndef.TNF_EXTERNAL_TYPE, 'eu.catlab.drinks', null, this.toByteArray(signedData)));

        return out;
    }

    /**
     *
     */
    public getLastTransactionDate() {
        return this.lastTransaction;
    }

    /**
     *
     */
    public getBalance() {
        return this.balance;
    }

    /**
     * Return this cards data in compact string format.
     */
    private serialize() {

        let out = '';

        out += this.toBytesInt32(this.balance);
        out += this.toBytesInt32(this.transactionCount);

        const timestamp = Math.floor(this.lastTransaction.getTime() / 1000);
        out += this.toBytesInt32(timestamp);

        for (let i = 0; i < this.previousTransactions.length; i ++) {
            out += this.toBytesInt32(this.previousTransactions[i]);
        }

        // discount
        out += this.toBytesInt8(this.discountPercentage);

        console.log('serialized ' + out.length + ' bytes');
        return out;
    }

    /**
     * @param data
     */
    private unserialize(data: string) {
        this.balance = this.fromBytesInt32(data.substr(0, 4));
        this.transactionCount = this.fromBytesInt32(data.substr(4, 4));

        const timestamp = this.fromBytesInt32(data.substr(8, 4));

        this.lastTransaction = new Date();
        this.lastTransaction.setTime(timestamp * 1000);

        this.previousTransactions = [];
        for (let i = 0; i < 5; i ++) {
            this.previousTransactions.push(this.fromBytesInt32(data.substr(12 + (i * 4), 4)));
        }

        // monday november 4th, 12:50am.
        // first addition that needs to be backwards compatible, without having any released versions.
        if (data.length > 32) {
            this.discountPercentage = this.fromBytesInt8(data.substr(32, 1));
        }

        console.log('unserialize', {
            balance: this.balance,
            transactionCount: this.transactionCount,
            previousTransactions: this.previousTransactions,
            lastTransaction: this.lastTransaction.toString(),
            discountPercentage: this.discountPercentage
        });
    }

    /**
     * SIGNING AND DATA CONVERSION
     */

    /**
     *
     */
    private getSignedData() {
        let out = this.serialize();

        const signature = this.nfcReader.hmac(this, out).toString(CryptoJS.enc.Latin1);
        out += signature;

        return out;
    }

    /**
     * @param byteArray
     */
    public parsePayload(byteArray: number[]) {

        const payload = byteArray.splice(0, byteArray.length - 32);

        const receivedSignature = this.toByteString(byteArray);
        const payloadBytestring = this.toByteString(payload);

        const signature = this.nfcReader.hmac(this, payloadBytestring).toString(CryptoJS.enc.Latin1);

        if (signature !== receivedSignature) {
            throw new SignatureMismatchException('Signature mismatch');
        }

        this.unserialize(payloadBytestring);
    }

    /**
     *
     */
    public setCorrupted() {
        this.corrupted = true;
    }

    /**
     *
     */
    public isCorrupted() {
        return this.corrupted;
    }

    /**
     * Apply a transaction and return the transaction id.
     * @param value
     */
    public applyTransaction(value: number) {
        this.transactionCount ++;
        this.balance += value;

        this.previousTransactions[this.transactionCount % 5] = value;
        this.lastTransaction = new Date();

        return this.transactionCount;
    }

    /**
     * Get the last 5 transactions in correct order.
     */
    public getPreviousTransactions(): number[] {
        const out: number[] = [];

        let lastNewIndex = this.transactionCount % 5;
        for (let i = 5; i > 0; i --) {
            out.push(this.previousTransactions[(lastNewIndex + i) % 5]);
        }
        return out;
    }

    /**
     * Return the data that will be sent to the server.
     */
    public getServerData(): any {
        return {
            transactionCount: this.transactionCount,
            balance: this.balance,
            previousTransactions: this.getPreviousTransactions(),
            discount: this.discountPercentage
        };
    }

    /**
     *
     */
    public async save() {
        await this.nfcReader.write(this);
        this.trigger('saved');
    }

    /**
     * Notify listeners that this card is ready for use.
     */
    public setReady() {
        this.ready = true;
        this.trigger('ready');
    }

    /**
     *
     */
    public getVisibleBalance() {
        return VisibleAmount.toVisible(this.balance);
    }

    /**
     *
     */
    public validate() {
        if (this.discountPercentage < 0 || this.discountPercentage > 100) {
            throw new CardValidationException('Discount percentage must be between 0 and 100');
        }
    }

    public addOrderTokenAlias(alias: string) {
        this.orderTokenAliases.push(alias);
    };

    public removeOrderTokenAlias(alias: string) {
        const index = this.orderTokenAliases.indexOf(alias);
        if (index > -1) {
            this.orderTokenAliases.splice(index, 1);
        }
    };

    /**
     * @param bytes
     */
    private toByteString(bytes: number[]) {
        let out = '';

        for (let i = 0; i < bytes.length; i ++) {
            out += String.fromCharCode(bytes[i]);
        }

        return out;
    }

    /**
     * @param string
     */
    private toByteArray(string: string) {
        const out = [];
        for (let i = 0; i < string.length; i ++) {
            out.push(string.charCodeAt(i));
        }
        return out;
    }

    private toBytesInt8(num: number) {
        return String.fromCharCode(num);
    }

    private fromBytesInt8(numString: string) {
        return numString.charCodeAt(0);
    }

    /**
     * @param num
     */
    private toBytesInt32(num: number) {
        var ascii='';
        for (let i=3;i>=0;i--) {
            ascii+=String.fromCharCode((num>>(8*i))&255);
        }
        return ascii;
    };

    /**
     * @param numString
     */
    private fromBytesInt32(numString: string) {
        var result=0;
        for (let i=3;i>=0;i--) {
            result+=numString.charCodeAt(3-i)<<(8*i);
        }
        return result;
    };
}
