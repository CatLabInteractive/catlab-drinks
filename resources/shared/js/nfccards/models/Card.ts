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
const ECDSA_SIGNATURE_LENGTH = 48;
const VERSION_HEADER_LENGTH = 1;
const DEVICE_ID_LENGTH = 3; // 3-byte unsigned device ID (big-endian), max 16,777,215
const V1_PREV_TX_COUNT = 5; // v1 cards store 5 previous transactions (same as v0)

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
     * The numeric device ID that last signed this card (v1 only).
     */
    public signerDeviceId: number = 0;

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
     * Return this cards data in compact string format (v0 format: 5 prev tx).
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
     * V1 serialize: stores 5 previous transactions (same as v0).
     * Uses unsigned integers for txCount and timestamp.
     * Total: balance(4) + txcount(4) + timestamp(4) + prev_tx(20) + discount(1) = 33 bytes
     */
    private serializeV1() {

        let out = '';

        out += this.toBytesInt32(this.balance);
        out += this.toBytesUint32(this.transactionCount);

        const timestamp = Math.floor(this.lastTransaction.getTime() / 1000);
        out += this.toBytesUint32(timestamp);

        // Only store last 5 previous transactions for v1 format
        for (let i = 0; i < V1_PREV_TX_COUNT; i ++) {
            out += this.toBytesInt32(this.previousTransactions[i] || 0);
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
     * Unserialize v1 card data (5 previous transactions).
     * Uses unsigned integers for txCount and timestamp.
     * @param data
     */
    private unserializeV1(data: string) {
        this.balance = this.fromBytesInt32(data.substr(0, 4));
        this.transactionCount = this.fromBytesUint32(data.substr(4, 4));

        const timestamp = this.fromBytesUint32(data.substr(8, 4));

        this.lastTransaction = new Date();
        this.lastTransaction.setTime(timestamp * 1000);

        this.previousTransactions = [];
        for (let i = 0; i < V1_PREV_TX_COUNT; i ++) {
            this.previousTransactions.push(this.fromBytesInt32(data.substr(12 + (i * 4), 4)));
        }

        if (data.length > 32) {
            this.discountPercentage = this.fromBytesInt8(data.substr(32, 1));
        }
    }

    /**
     * SIGNING AND DATA CONVERSION
     */

    /**
     * Get signed data for writing to the NFC card.
     * Always writes in the latest version format.
     */
    private getSignedData() {

        if (this.keyManager && this.keyManager.isInitialized()) {
            // Version 1: Asymmetric ECDSA signing with compact card data
            let out = '';

            // 1-byte version header
            out += this.toBytesInt8(CARD_VERSION_ASYMMETRIC);

            // 3-byte unsigned device ID (big-endian)
            out += this.toBytesUint24(this.keyManager.getDeviceId());

            // Card data payload (v1: 5 previous transactions)
            out += this.serializeV1();

            // 48-byte ECDSA P-192 signature over (version + deviceId + payload + cardUid)
            const dataToSign = out + this.uid;
            const signature = this.keyManager.sign(dataToSign);
            out += signature;

            return out;
        } else {
            // Version 0: Legacy HMAC signing
            let payload = this.serialize();
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
     * V1 cards start with 0x01 in the first byte.
     * V0 cards: the first byte is the high byte of the balance (signed int32).
     * For positive balances < 16M cents (€167,772), the first byte is 0x00.
     * For negative balances, the first byte is 0xFF (or similar).
     * Only 0x01 is treated as v1; everything else is v0.
     */
    private detectVersion(byteArray: number[]): number {
        if (byteArray.length < 1) {
            return CARD_VERSION_LEGACY;
        }
        if (byteArray[0] === CARD_VERSION_ASYMMETRIC) {
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
     * Parse v1 payload (ECDSA signed, compact card data).
     */
    private parseV1Payload(byteArray: number[]) {
        this.dataVersion = CARD_VERSION_ASYMMETRIC;

        // Split: version(1) + deviceId(3) + data(variable) + signature(48)
        const versionBytes = byteArray.splice(0, VERSION_HEADER_LENGTH);
        const deviceIdBytes = byteArray.splice(0, DEVICE_ID_LENGTH);
        const signatureBytes = byteArray.splice(byteArray.length - ECDSA_SIGNATURE_LENGTH, ECDSA_SIGNATURE_LENGTH);
        const payloadBytes = byteArray;

        this.signerDeviceId = this.fromBytesUint24(this.toByteString(deviceIdBytes));

        const payloadBytestring = this.toByteString(payloadBytes);
        const signatureBytestring = this.toByteString(signatureBytes);

        // Reconstruct what was signed: version + deviceId + payload + cardUid
        const versionStr = this.toByteString(versionBytes);
        const deviceIdStr = this.toByteString(deviceIdBytes);
        const dataToVerify = versionStr + deviceIdStr + payloadBytestring + this.uid;

        // Try asymmetric verification using numeric device ID
        if (this.keyManager && this.keyManager.verify(this.signerDeviceId, dataToVerify, signatureBytestring)) {
            this.unserializeV1(payloadBytestring);
            return;
        }

        throw new SignatureMismatchException('V1 signature verification failed for device ID ' + this.signerDeviceId);
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

    /**
     * Encode a 32-bit unsigned integer as a 4-byte big-endian string.
     * Valid for values 0 to 4,294,967,295. Values above 2^31-1 require
     * multiplication instead of bit shift to avoid sign extension.
     * @param num
     */
    private toBytesUint32(num: number) {
        return String.fromCharCode((num >>> 24) & 255) +
               String.fromCharCode((num >>> 16) & 255) +
               String.fromCharCode((num >>> 8) & 255) +
               String.fromCharCode(num & 255);
    }

    /**
     * Decode a 4-byte big-endian string as a 32-bit unsigned integer.
     * Uses unsigned right shift to avoid sign extension.
     * @param numString
     */
    private fromBytesUint32(numString: string) {
        return (
            (numString.charCodeAt(0) * 0x1000000) +
            (numString.charCodeAt(1) * 0x10000) +
            (numString.charCodeAt(2) * 0x100) +
            numString.charCodeAt(3)
        );
    }

    /**
     * Encode a 24-bit unsigned integer as a 3-byte big-endian string.
     * Max value: 16,777,215 (0xFFFFFF).
     * @param num
     */
    private toBytesUint24(num: number) {
        return String.fromCharCode((num >> 16) & 255) +
               String.fromCharCode((num >> 8) & 255) +
               String.fromCharCode(num & 255);
    }

    /**
     * Decode a 3-byte big-endian string as a 24-bit unsigned integer.
     * @param numString
     */
    private fromBytesUint24(numString: string) {
        return ((numString.charCodeAt(0) << 16) |
                (numString.charCodeAt(1) << 8) |
                numString.charCodeAt(2)) >>> 0;
    }
}
