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
import {SignatureMismatch} from "../exceptions/SignatureMismatch";

export class Card {

    public balance: number = 0;

    public transactionCount: number = 0;

    public loaded = false;

    private previousTransactions = [
        0,
        0,
        0,
        0,
        0
    ];

    public lastTransaction: Date = new Date();

    private corrupted: boolean;

    constructor(
        private nfcReader: NfcReader,
        private uid: string
    ) {
        this.corrupted = false;
    }

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

    public getLastTransaction() {
        return this.lastTransaction;
    }

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

        console.log('serialized ' + out.length + ' bytes');
        return out;
    }

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

        this.previousTransactions = [ 0, 0, 0, 0, 0 ];

        console.log('unserialize', {
            balance: this.balance,
            transactionCount: this.transactionCount,
            previousTransactions: this.previousTransactions,
            lastTransaction: this.lastTransaction.toString()
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
            throw new SignatureMismatch('Signature mismatch');
        }

        this.unserialize(payloadBytestring);

    }

    /**
     *
     */
    public setCorrupted()
    {
        this.corrupted = true;
    }

    public isCorrupted() {
        return this.corrupted;
    }

    /**
     *
     */
    public async save() {
        await this.nfcReader.write(this);
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
        for (let i = 0; i < 5; i ++) {
            out.push(this.previousTransactions[(lastNewIndex + i) % 5]);
        }

        return out;
    }

    public getServerData(): any {
        return {
            transactionCount: this.transactionCount,
            balance: this.balance,
            previousTransactions: this.getPreviousTransactions()
        };
    }

    private toByteString(bytes: number[]) {
        let out = '';

        for (let i = 0; i < bytes.length; i ++) {
            out += String.fromCharCode(bytes[i]);
        }

        return out;
    }

    private toByteArray(string: string) {
        const out = [];
        for (let i = 0; i < string.length; i ++) {
            out.push(string.charCodeAt(i));
        }
        return out;
    }

    private toBytesInt32(num: number) {
        var ascii='';
        for (let i=3;i>=0;i--) {
            ascii+=String.fromCharCode((num>>(8*i))&255);
        }
        return ascii;
    };

    private fromBytesInt32(numString: string) {
        var result=0;
        for (let i=3;i>=0;i--) {
            result+=numString.charCodeAt(3-i)<<(8*i);
        }
        return result;
    };
}
