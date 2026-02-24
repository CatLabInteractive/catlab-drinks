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

import { ec as EC } from 'elliptic';
import * as CryptoJS from 'crypto-js';

const curve = new EC('p256');

/** ECDSA signature: 32 bytes r + 32 bytes s = 64 bytes */
export const ECDSA_SIGNATURE_LENGTH = 64;

/**
 * Key pair entry for a device.
 */
export interface PublicKeyEntry {
	id: number;
	uid: string;
	public_key: string;
	approved_at: string | null;
}

/**
 * Manages ECDSA key pairs for NFC card signing.
 *
 * Uses compact recovery signatures (33 bytes) to fit within NTAG213's 144-byte limit.
 * Private keys are encrypted with the device secret (from server) and stored in localStorage.
 * Public keys are uploaded to the server for admin approval.
 */
export class KeyManager {

	private keyPair: EC.KeyPair | null = null;
	private publicKeys: Map<string, EC.KeyPair> = new Map();
	private publicKeysByDeviceId: Map<number, EC.KeyPair> = new Map();
	private deviceUid: string = '';
	private deviceId: number = 0;

	/**
	 * Check if a key pair already exists in localStorage for a given device.
	 * Does NOT load it (that requires the device secret).
	 * @param deviceUid The unique device identifier
	 */
	public hasStoredKeyPair(deviceUid: string): boolean {
		const storageKey = 'catlab_drinks_device_keypair_' + deviceUid;
		return localStorage.getItem(storageKey) !== null;
	}

	/**
	 * Generate a new key pair, encrypt it, and store it.
	 * This is the explicit "Generate Credentials" action.
	 * @param deviceUid The unique device identifier
	 * @param deviceId The numeric device ID
	 * @param deviceSecret The device secret from the server
	 */
	public generateKeyPair(deviceUid: string, deviceId: number, deviceSecret: string): void {
		this.deviceUid = deviceUid;
		this.deviceId = deviceId;

		this.keyPair = curve.genKeyPair();
		const privateKeyHex = this.keyPair.getPrivate('hex');
		const serialized = JSON.stringify({ privateKey: privateKeyHex });
		const encrypted = CryptoJS.AES.encrypt(serialized, deviceSecret).toString();

		const storageKey = 'catlab_drinks_device_keypair_' + deviceUid;
		localStorage.setItem(storageKey, encrypted);
	}

	/**
	 * Initialize key manager with device info and secret.
	 * Loads an existing key pair from localStorage. Does NOT generate one.
	 * @param deviceUid The unique device identifier
	 * @param deviceId The numeric device ID
	 * @param deviceSecret The device secret from the server (used to decrypt private key)
	 */
	public initialize(deviceUid: string, deviceId: number, deviceSecret: string): void {
		this.deviceUid = deviceUid;
		this.deviceId = deviceId;

		const storageKey = 'catlab_drinks_device_keypair_' + deviceUid;
		const stored = localStorage.getItem(storageKey);

		if (stored) {
			try {
				const decrypted = CryptoJS.AES.decrypt(stored, deviceSecret).toString(CryptoJS.enc.Utf8);
				if (decrypted) {
					const parsed = JSON.parse(decrypted);
					this.keyPair = curve.keyFromPrivate(parsed.privateKey, 'hex');
				}
			} catch (e) {
				console.warn('Failed to decrypt stored key pair');
				this.keyPair = null;
			}
		}
	}

	/**
	 * Get the public key in hex format.
	 */
	public getPublicKeyHex(): string {
		if (!this.keyPair) {
			throw new Error('KeyManager not initialized');
		}
		return this.keyPair.getPublic('hex');
	}

	/**
	 * Get the device UID.
	 */
	public getDeviceUid(): string {
		return this.deviceUid;
	}

	/**
	 * Get the device numeric ID.
	 */
	public getDeviceId(): number {
		return this.deviceId;
	}

	/**
	 * Load approved public keys from the server response.
	 * Indexes by both UID (string) and numeric device ID for card verification.
	 * @param keys Array of public key entries from the API
	 */
	public loadPublicKeys(keys: PublicKeyEntry[]): void {
		this.publicKeys.clear();
		this.publicKeysByDeviceId.clear();
		for (const entry of keys) {
			if (entry.public_key && entry.approved_at) {
				try {
					const key = curve.keyFromPublic(entry.public_key, 'hex');
					this.publicKeys.set(entry.uid, key);
					this.publicKeysByDeviceId.set(entry.id, key);
				} catch (e) {
					console.warn('Failed to load public key for device ' + entry.uid);
				}
			}
		}
	}

	/**
	 * Sign data with the device's private key.
	 * Returns a standard ECDSA signature (64 bytes: 32 bytes r + 32 bytes s).
	 * @param data The data string to sign
	 * @returns Signature as a 64-byte string
	 */
	public sign(data: string): string {
		if (!this.keyPair) {
			throw new Error('KeyManager not initialized');
		}

		const hash = CryptoJS.SHA256(data).toString(CryptoJS.enc.Hex);
		const signature = this.keyPair.sign(hash, { canonical: true });

		// Standard format: 32 bytes r + 32 bytes s = 64 bytes
		const r = signature.r.toString('hex').padStart(64, '0');
		const s = signature.s.toString('hex').padStart(64, '0');

		return this.hexToByteString(r + s);
	}

	/**
	 * Verify an ECDSA signature against data using a specific device's public key.
	 * @param signerDeviceId The numeric ID or UID of the device that signed the data
	 * @param data The original data string
	 * @param signatureBytes The signature as a byte string (64 bytes: r + s)
	 * @returns True if the signature is valid
	 */
	public verify(signerDeviceId: number | string, data: string, signatureBytes: string): boolean {
		let publicKey: EC.KeyPair | undefined;

		if (typeof signerDeviceId === 'number') {
			publicKey = this.publicKeysByDeviceId.get(signerDeviceId);
		} else {
			publicKey = this.publicKeys.get(signerDeviceId);
		}

		if (!publicKey) {
			return false;
		}

		const hash = CryptoJS.SHA256(data).toString(CryptoJS.enc.Hex);
		const sigHex = this.byteStringToHex(signatureBytes);

		const r = sigHex.substring(0, 64);
		const s = sigHex.substring(64, 128);

		try {
			return publicKey.verify(hash, { r: r, s: s });
		} catch (e) {
			return false;
		}
	}

	/**
	 * Check if we have the public key for a given device.
	 * @param deviceUid Device UID string
	 */
	public hasPublicKey(deviceUid: string): boolean {
		return this.publicKeys.has(deviceUid);
	}

	/**
	 * Check if we have the public key for a given device by numeric ID.
	 * @param deviceId Numeric device ID
	 */
	public hasPublicKeyById(deviceId: number): boolean {
		return this.publicKeysByDeviceId.has(deviceId);
	}

	/**
	 * Check if this key manager has been initialized with a key pair.
	 */
	public isInitialized(): boolean {
		return this.keyPair !== null;
	}

	/**
	 * Check if this device's public key has been approved.
	 * @param approvedAt The approved_at timestamp from the device API response
	 */
	public isApproved(approvedAt: string | null): boolean {
		return approvedAt !== null;
	}

	/**
	 * Convert hex string to byte string.
	 */
	private hexToByteString(hex: string): string {
		let str = '';
		for (let i = 0; i < hex.length; i += 2) {
			str += String.fromCharCode(parseInt(hex.substr(i, 2), 16));
		}
		return str;
	}

	/**
	 * Convert byte string to hex string.
	 */
	private byteStringToHex(str: string): string {
		let hex = '';
		for (let i = 0; i < str.length; i++) {
			hex += str.charCodeAt(i).toString(16).padStart(2, '0');
		}
		return hex;
	}
}
