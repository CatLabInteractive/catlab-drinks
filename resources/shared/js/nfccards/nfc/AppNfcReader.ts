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
import {Card} from "../models/Card";
import {InvalidMessageException} from "../exceptions/InvalidMessageException";
import {NfcWriteException} from "../exceptions/NfcWriteException";
import {CorruptedCardException} from "../exceptions/CorruptedCardException";

/**
 * NFC reader that communicates with the CatLab Drinks capacitor app
 * via the window.CATLAB_DRINKS_APP.nfc interface.
 */
export class AppNfcReader extends NfcReader {

	private nfc: any;

	constructor(
		offlineStore: OfflineStore,
		logger: Logger
	) {
		super(offlineStore, logger);
	}

	connect(url: string, password: string, handleHandshake = true) {
		this.executeHandshake = handleHandshake;

		this.nfcReaderPassword = password;

		// @ts-ignore
		const CatLabDrinksNFC = window.CATLAB_DRINKS_APP.CatLabDrinksNFC;
		this.nfc = new CatLabDrinksNFC({
			log: (uid: string, msg: string, data: any) => {
				this.logger.log(uid, msg, data);
			}
		});

		this.nfc.connect();

		this.trigger('connection:change', true);

		this.nfc.on('card:connect', async (cardInfo: any) => {

			const uid = cardInfo.uid;
			const card = new Card(this, uid);
			card.setTopupDomain(this.topupDomain);

			if (this.executeHandshake) {
				const cardPassword = this.calculateCardPassword(uid);
				this.nfc.setCardPassword(cardPassword);

				this.currentCard = card;
			}

			this.trigger('card:connect', card);
			this.logger.log(card.getUid(), 'connected');

			if (this.executeHandshake) {
				try {
					await this.nfc.authenticate();
				} catch (e: any) {
					this.currentCard!.setCorrupted();
					this.trigger('card:loaded', this.currentCard);
					return;
				}

				try {
					await this.setCardData(card);
				} catch (e) {
					if (e instanceof CorruptedCardException) {
						this.currentCard!.setCorrupted();
						this.trigger('card:corrupted', this.currentCard);
					} else {
						throw e;
					}
				}

				this.logger.log(card.getUid(), 'Card finished loading');
				this.trigger('card:loaded', this.currentCard);
			}
		});

		this.nfc.on('card:disconnect', (cardInfo: any) => {
			this.currentCard = null;
			this.trigger('card:disconnect');
			this.logger.log(cardInfo?.uid || '', 'disconnected');
		});

		this.nfc.on('card:error', (error: any) => {
			this.logger.log('', 'Card error', error);
		});
	}

	/**
	 * @param card
	 */
	private async setCardData(card: Card) {
		const records = await this.nfc.readNdef();

		if (records && records.length > 0) {
			try {
				card.parseNdef(records);

				this.logger.log(card.getUid(), 'NDEF messages parsed', records);

				this.logger.log(card.getUid(), 'Setting card state in offline store');
				const byteArray = ndef.encodeMessage(records);
				const base64 = btoa(this.bin2string(byteArray));
				await this.offlineStore.setCardState(card.getUid(), base64);
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
		} else {
			// No NDEF records found
			const isNew = await this.nfc.isNewCard();
			if (isNew) {
				// this is a brand new card.
				this.logger.log(card.getUid(), 'New card detected, initializing and writing empty data.');
				await this.nfc.initializeNewCard(card.getUid());
				await this.write(card);
			} else {
				// not a new card, try to recover the data from local data.
				await this.recoverInvalidContent(card);
				await this.write(card);
			}
		}
	}

	public async write(card: Card): Promise<void> {
		if (this.currentCard === null || this.currentCard.getUid() !== card.getUid()) {
			this.logger.log(card.getUid(), 'Current card doesnt have the same uid as the data card');
			return;
		}

		let messages = card.getNdefMessages();
		console.log('WRITING NDEF MESSAGES', messages);

		try {
			let byteArray = ndef.encodeMessage(messages);
			const base64 = btoa(this.bin2string(byteArray));
			await this.offlineStore.setCardState(card.getUid(), base64);

			await this.nfc.write(card.getUid(), messages);
		} catch (e) {
			console.log(e);
			throw new NfcWriteException('error writing to card');
		}
	}

}
