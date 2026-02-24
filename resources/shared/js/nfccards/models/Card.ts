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
import {KeyManager} from "../crypto/KeyManager";

/** NFC card data format versions */
export const CARD_VERSION_LEGACY = 0;
export const CARD_VERSION_ASYMMETRIC = 1;

/** Signature sizes per version */
const HMAC_SIGNATURE_LENGTH = 32;
const ECDSA_SIGNATURE_LENGTH = 64;
const VERSION_HEADER_LENGTH = 2;
const DEVICE_UID_LENGTH = 36;

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

    /**
     * The version of the card data format that was read.
     */
    public dataVersion: number = CARD_VERSION_LEGACY;

    /**
     * The device UID that last signed this card (v1 only).
     */
    public signerDeviceUid: string = '';

    private corrupted: boolean;

    private topupDomain: string = 'd.ctlb.eu';

    private keyManager: KeyManager | null = null;

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
     * Set the key manager for asymmetric signing.
     * @param keyManager
     */
    public setKeyManager(keyManager: KeyManager) {
        this.keyManager = keyManager;
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
        const out: any = [];

        // first, the topup url.
        out.push(ndef.uriRecord("http://" + this.topupDomain + "/" + this.uid));

        // next, our own internal (and signed) state
        const signedData = this.getSignedData();
        out.push(ndef.record(ndef.TNF_EXTERNAL_TYPE, 'eu.catlab.drinks', null, this.toByteArray(signedData)));

        return out;
    }

    /**
     * Set the topup domain for NFC card URLs.
     * @param domain
     */
    public setTopupDomain(domain: string) {
        this.topupDomain = domain;
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

        /*
        console.log('unserialize', {
            balance: this.balance,
            transactionCount: this.transactionCount,
            previousTransactions: this.previousTransactions,
            lastTransaction: this.lastTransaction.toString(),
            discountPercentage: this.discountPercentage
        });*/
    }

    /**
     * SIGNING AND DATA CONVERSION
     */

    /**
     * Get signed data for writing to the NFC card.
     * Always writes in the latest version format.
     */
    private getSignedData() {
        let payload = this.serialize();

        if (this.keyManager && this.keyManager.isInitialized()) {
            // Version 1: Asymmetric ECDSA signing
            let out = '';

            // 2-byte version header
            out += this.toBytesInt16(CARD_VERSION_ASYMMETRIC);

            // 36-byte device UID
            const deviceUid = this.keyManager.getDeviceUid();
            out += this.padOrTruncate(deviceUid, DEVICE_UID_LENGTH);

            // Card data payload
            out += payload;

            // 64-byte ECDSA signature over (version + deviceUid + payload + cardUid)
            const dataToSign = out + this.uid;
            const signature = this.keyManager.sign(dataToSign);
            out += signature;

            return out;
        } else {
            // Version 0: Legacy HMAC signing
            const signature = this.nfcReader.hmac(this, payload).toString(CryptoJS.enc.Latin1);
            payload += signature;
            return payload;
        }
    }

    /**
     * @param byteArray
     */
    public parsePayload(byteArray: number[]) {

        // Detect version from the first 2 bytes
        const version = this.detectVersion(byteArray);

        if (version === CARD_VERSION_ASYMMETRIC) {
            this.parseV1Payload(byteArray);
        } else {
            this.parseV0Payload(byteArray);
        }
    }

    /**
     * Detect the card version from the payload.
     * V1 cards start with 0x0001 in the first 2 bytes.
     * V0 cards: since balance is stored as a 32-bit int and no user has >83k credits,
     * the first 2 bytes are always 0x0000.
     */
    private detectVersion(byteArray: number[]): number {
        if (byteArray.length < 2) {
            return CARD_VERSION_LEGACY;
        }
        const versionValue = (byteArray[0] << 8) | byteArray[1];
        if (versionValue === CARD_VERSION_ASYMMETRIC) {
            return CARD_VERSION_ASYMMETRIC;
        }
        return CARD_VERSION_LEGACY;
    }

    /**
     * Parse legacy v0 payload (HMAC-SHA256 signed).
     */
    private parseV0Payload(byteArray: number[]) {
        this.dataVersion = CARD_VERSION_LEGACY;

        const payload = byteArray.splice(0, byteArray.length - HMAC_SIGNATURE_LENGTH);

        const receivedSignature = this.toByteString(byteArray);
        const payloadBytestring = this.toByteString(payload);

        const signature = this.nfcReader.hmac(this, payloadBytestring).toString(CryptoJS.enc.Latin1);

        if (signature !== receivedSignature) {
            throw new SignatureMismatchException('Signature mismatch');
        }

        this.unserialize(payloadBytestring);
    }

    /**
     * Parse v1 payload (ECDSA asymmetric signed).
     */
    private parseV1Payload(byteArray: number[]) {
        this.dataVersion = CARD_VERSION_ASYMMETRIC;

        // Split: version(2) + deviceUid(36) + data(variable) + signature(64)
        const versionBytes = byteArray.splice(0, VERSION_HEADER_LENGTH);
        const deviceUidBytes = byteArray.splice(0, DEVICE_UID_LENGTH);
        const signatureBytes = byteArray.splice(byteArray.length - ECDSA_SIGNATURE_LENGTH, ECDSA_SIGNATURE_LENGTH);
        const payloadBytes = byteArray;

        this.signerDeviceUid = this.toByteString(deviceUidBytes).replace(/\0+$/, '');

        const payloadBytestring = this.toByteString(payloadBytes);
        const signatureBytestring = this.toByteString(signatureBytes);

        // Reconstruct what was signed: version + deviceUid + payload + cardUid
        const versionStr = this.toByteString(versionBytes);
        const deviceUidStr = this.toByteString(deviceUidBytes);
        const dataToVerify = versionStr + deviceUidStr + payloadBytestring + this.uid;

        // Try asymmetric verification first
        if (this.keyManager && this.keyManager.verify(this.signerDeviceUid, dataToVerify, signatureBytestring)) {
            this.unserialize(payloadBytestring);
            return;
        }

        throw new SignatureMismatchException('V1 signature verification failed for device ' + this.signerDeviceUid);
    }

    /**
     *
     */
    public setCorrupted() {
		this.trigger('corrupted');
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
        const data: any = {
            transactionCount: this.transactionCount,
            balance: this.balance,
            previousTransactions: this.getPreviousTransactions(),
            discount: this.discountPercentage
        };

        return data;
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

    public removeAllOrderTokenAliases() {
        while (this.orderTokenAliases.length > 0) {
            this.orderTokenAliases.pop();
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
     * Encode a 16-bit unsigned integer as a 2-byte big-endian string.
     * @param num
     */
    private toBytesInt16(num: number) {
        return String.fromCharCode((num >> 8) & 255) + String.fromCharCode(num & 255);
    }

    /**
     * Pad or truncate a string to a fixed length.
     * @param str
     * @param length
     */
    private padOrTruncate(str: string, length: number): string {
        if (str.length > length) {
            return str.substr(0, length);
        }
        while (str.length < length) {
            str += '\0';
        }
        return str;
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
