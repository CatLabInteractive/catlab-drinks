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

import {TransactionStore} from "./store/TransactionStore";
import {NfcReader} from "./nfc/NfcReader";
import {Eventable} from "../utils/Eventable";
import {Card} from "./models/Card";
import {OfflineStore} from "./store/OfflineStore";
import {Logger} from "./tools/Logger";
import {NfcWriteException} from "./exceptions/NfcWriteException";
import {OfflineException} from "./exceptions/OfflineException";
import {InsufficientFundsException} from "./exceptions/InsufficientFundsException";
import {NoCardFoundException} from "./exceptions/NoCardFoundException";
import {Transaction} from "./models/Transaction";
import {CorruptedCardException} from "./exceptions/CorruptedCardException";
import {RemoteNfcReader} from "./nfc/RemoteNfcReader";
import {AppNfcReader} from "./nfc/AppNfcReader";
import {KeyManager, PublicKeyEntry} from "./crypto/KeyManager";

/**
 *
 */
export class CardService extends Eventable {

	/**
	 *
	 */
	private password: string = '';

	/**
	 *
	 */
	private transactionStore: TransactionStore;

	/**
	 *
	 */
	private nfcReader: NfcReader;

	/**
	 *
	 */
	private readonly offlineStore: OfflineStore;

	/**
	 *
	 */
	private readonly logger: Logger;

	/**
	 *
	 */
	private currentCard: Card | null = null;

	/**
	 *
	 */
	private isCardLoaded = false;

	private connected:boolean = false;

	private skipRefreshWhenBadInternetConnection = false;

	private axios: any = null;

	private failedTransactions: Map<number, Transaction>;

	public hasCardReader: boolean = false;

	private keyManager: KeyManager | null = null;

	/**
	 * Tracks the key approval status from the server.
	 * 'none' = no key generated, 'pending' = awaiting approval, 'approved' = ready to use.
	 */
	private keyApprovalStatus: 'none' | 'pending' | 'approved' = 'none';

	/**
	 *
	 */
	constructor(
		axios: any,
		organisationId: string
	) {
		super();

		this.axios = axios;
		this.offlineStore = new OfflineStore(organisationId);
		this.transactionStore = new TransactionStore(axios, organisationId, this.offlineStore);
		this.logger = new Logger();

		// @ts-ignore
		if (typeof(window.CATLAB_DRINKS_APP) !== 'undefined' && window.CATLAB_DRINKS_APP.nfc) {
			this.nfcReader = new AppNfcReader(this.offlineStore, this.logger);
		} else {
			this.nfcReader = new RemoteNfcReader(this.offlineStore, this.logger);
		}
		this.failedTransactions = this.transactionStore.readFailedTransactions();
	}

	/**
	 * @param nfcService
	 * @param nfcPassword
	 */
	public connect(nfcService: string, nfcPassword: string) {

		this.hasCardReader = true;

		this.nfcReader.on('connection:change', (connection: boolean) => {
			this.connected = connection;
			this.trigger('connection:change', connection);
		});

		this.nfcReader.connect(nfcService, nfcPassword);

		// events
		this.nfcReader.on('card:connect', (card: Card) => {
			this.trigger('card:connect', card);
		});

		this.nfcReader.on('card:disconnect', (card: Card) => {
			this.isCardLoaded = false;
			this.trigger('card:disconnect', card);
		});

		this.nfcReader.on('card:loaded', async (card: Card) => {

			// Block card operations if key is not approved
			if (!this.isCardOperationAllowed()) {
				card.setCorrupted();
				this.trigger('card:blocked', card);
				return;
			}

			// Inject key manager for v1 signing/verification
			if (this.keyManager) {
				card.setKeyManager(this.keyManager);
			}

			// check if this card is corrupt
			await this.checkIfCardIsCorrupt(card);

			// don't handle corrupt cards.
			if (card.isCorrupted()) {
				this.trigger('card:corrupt', card);
				return;
			}

			this.currentCard = card;
			this.isCardLoaded = false;

			// check if there are any transactions that still need to be processed
			if (!this.skipRefreshWhenBadInternetConnection || this.hasApiConnection()) {
				await this.refreshCard(card);
			}

			// check if we still have a card (it might be disconnected by now)
			// if so, apply triggers.
			if (this.currentCard === card) {
				this.isCardLoaded = true;

				this.currentCard.setReady();
				this.trigger('card:loaded', this.currentCard);
				this.trigger('card:balance:change', this.currentCard);
			}
		});
	}

	/**
	 *
	 */
	public isConnected() {
		return this.connected;
	}

	/**
	 * Check if we have an active connection to the api.
	 * If this method returns false, the reader will work in 'offline' mode and no api requests will be made that
	 * might affect usability. Transactions will still be pushed in the background, but any pending transactions
	 * will not be synced from this specific terminal.
	 */
	public hasApiConnection() {
		return true;
	}

	/**
	 * If connected to the internet:
	 * - load any pending transactions that might still be online
	 * - upload the card data to the server so that any missing
	 *   transactions can be processed.
	 *
	 * If not connected to the internet:
	 * do nothing.
	 * @param card
	 * @param forceWrite
	 */
	async refreshCard(card: Card, forceWrite = false) {
		const now = new Date();

		this.logger.log(card.getUid(), 'Refreshing card');

		this.logger.log(card.getUid(), 'Loading server data');

		const serverCard = await this.transactionStore.getCard(card.getUid(), true);
		if (serverCard) {

			this.logger.log(card.getUid(), 'Server data found!', serverCard);

			// set interla id
			card.id = serverCard.id;
			card.orderTokenAliases = serverCard.orderTokenAliases;

			// check for pending transactions
			const pendingTransactions = serverCard.pendingTransactions.items;
			if (pendingTransactions.length > 0) {

				this.logger.log(card.getUid(), 'Apply ' + pendingTransactions.length + ' pending transactions');

				// apply each transaction to the card
				try {
					pendingTransactions.forEach(
						(transaction: any) => {
							transaction.card_transaction = card.applyTransaction(transaction.value);
							transaction.has_synced = true;
							transaction.card_date = this.transactionStore.toApiDate(now);
						}
					);

					// save the card

					this.logger.log(card.getUid(), 'Saving applied pending transactions');
					await card.save();

					await this.transactionStore.updateTransactions(pendingTransactions);

					this.logger.log(card.getUid(), 'Done saving applied pending transactions');

				} catch (e) {
					if (e instanceof NfcWriteException) {
						// write failed? Revert and mark these transactions back as pending.
						// mark all these transactions as pending
						await this.transactionStore.markTransactionsAsPending(pendingTransactions);

					}
				}

			} else if(forceWrite) {
				// no content but still want to save?
				await card.save();
			}

			// upload current values so that server can update its list of transactions

			this.logger.log(card.getUid(), 'Uploading card state');
			await this.uploadCardData(card);
			this.logger.log(card.getUid(), 'Done uploading card state');
		}
	}

	async getCardFromUid(cardUid: string) {
		return await this.transactionStore.getCard(cardUid);
	}

	async uploadCardData(card: Card) {
		if (!card.id) {
			throw 'Card without id cannot be uploaded to server.';
		}

		await this.transactionStore.uploadCardData(card.id.toString(), card);
	}

	/**
	 * This method is potentially dangerous.
	 * This will rebuild the card data based on the transactions that are known
	 * to the server. Transactions that have not been uploaded will thus not be
	 * taken into account. Sales might get lost and the credit provided here
	 * might be too high.
	 * @param card
	 */
	async rebuild(card: Card) {
		if (!this.transactionStore.isOnline()) {
			throw new OfflineException('rebuild only works with an active internet connection');
		}

		// load the server card
		const serverCard = await this.transactionStore.getCard(card.getUid(), true);

		// reset the last known sync id.
		this.offlineStore.setLastKnownSyncId(card.getUid(), 0);

		// mark all online transactions as 'pending'
		await this.transactionStore.setAllTransactionsPending(serverCard.id);

		// format the card
		card.balance = 0;
		card.transactionCount = 0;
		card.previousTransactions = [0,0,0,0,0];
		card.lastTransaction = new Date();

		await this.refreshCard(card, true);
	}

	/**
	 * @param password
	 */
	setPassword(password: string) {
		this.password = password;
		this.nfcReader.setPassword(password);
		return this;
	}

	/**
	 * Initialize asymmetric key management for this device.
	 * Only loads an existing key pair - does NOT generate one.
	 * Call generateAndRegisterKey() for explicit key generation.
	 * @param deviceUid The device's unique identifier
	 * @param deviceId The device's numeric ID
	 * @param deviceSecret The device secret (from server API)
	 */
	initializeKeyManager(deviceUid: string, deviceId: number, deviceSecret: string) {
		this.keyManager = new KeyManager();
		this.keyManager.initialize(deviceUid, deviceId, deviceSecret);
		return this;
	}

	/**
	 * Get the key manager instance.
	 */
	getKeyManager(): KeyManager | null {
		return this.keyManager;
	}

	/**
	 * Check if this device has a stored key pair (without decrypting it).
	 * @param deviceUid The device's unique identifier
	 */
	hasStoredKeyPair(deviceUid: string): boolean {
		if (this.keyManager) {
			return this.keyManager.hasStoredKeyPair(deviceUid);
		}
		return new KeyManager().hasStoredKeyPair(deviceUid);
	}

	/**
	 * Generate a new key pair and register it with the server.
	 * This is the explicit "Generate Credentials" action.
	 * @param deviceUid The device's unique identifier
	 * @param deviceId The device's numeric ID
	 * @param deviceSecret The device secret (from server API)
	 */
	async generateAndRegisterKey(deviceUid: string, deviceId: number, deviceSecret: string): Promise<any> {
		if (!this.keyManager) {
			this.keyManager = new KeyManager();
		}

		this.keyManager.generateKeyPair(deviceUid, deviceId, deviceSecret);
		return await this.registerPublicKey();
	}

	/**
	 * Load approved public keys from the server.
	 * @param keys Array of public key entries
	 */
	loadPublicKeys(keys: PublicKeyEntry[]) {
		if (this.keyManager) {
			this.keyManager.loadPublicKeys(keys);
		}
		return this;
	}

	/**
	 * Register this device's public key with the server.
	 * @returns Promise with the updated device data
	 */
	async registerPublicKey(): Promise<any> {
		if (!this.keyManager) {
			throw new Error('Key manager not initialized');
		}

		const publicKey = this.keyManager.getPublicKeyHex();

		const response = await this.axios({
			method: 'put',
			url: 'devices/current',
			data: {
				public_key: publicKey
			}
		});

		return response.data;
	}

	/**
	 * Fetch approved public keys from the server.
	 * @param organisationId
	 */
	async fetchApprovedPublicKeys(organisationId: string): Promise<PublicKeyEntry[]> {
		const response = await this.axios.get(
			'organisations/' + organisationId + '/approved-public-keys'
		);
		return response.data.items || [];
	}

	/**
	 * Get the key approval status.
	 * Returns 'none' if no key pair exists, 'pending' if key exists but not approved,
	 * 'approved' if key is approved.
	 */
	getKeyStatus(): 'none' | 'pending' | 'approved' {
		return this.keyApprovalStatus;
	}

	/**
	 * Set the key approval status.
	 * Should be called after checking the device's approved_at from the server.
	 */
	setKeyApprovalStatus(status: 'none' | 'pending' | 'approved') {
		this.keyApprovalStatus = status;
		this.trigger('keyStatus:change', status);
	}

	/**
	 * Check whether card operations (scan/sign) should be allowed.
	 * Only allowed when key is approved.
	 */
	isCardOperationAllowed(): boolean {
		return this.keyApprovalStatus === 'approved';
	}

	/**
	 * @param domain
	 */
	setTopupDomain(domain: string) {
		this.nfcReader.setTopupDomain(domain);
		return this;
	}

	getCard() {
		if (!this.isCardLoaded) {
			return null;
		}

		return this.currentCard;
	}

	/**
	 * @param topupUid
	 * @param amount
	 */
	async topup(topupUid: string, amount: number) {
		const card = this.getCard();
		if (!card) {
			throw new NoCardFoundException('No card found.');
		}

		// try to write the transaction to card
		this.recoverTransactionIfNeeded(card);
		const transactionNumber = card.applyTransaction(amount);
		const transaction = new Transaction(
			card.getUid(),
			transactionNumber,
			'topup',
			new Date(),
			amount,
			null,
			topupUid
		);

		await this.persist(transaction, card);

		// yay! save that transaction (but don't wait for upload)
		await this.offlineStore.addPendingTransaction(transaction);

		// and refresh the card.
		await this.refreshCard(card);

		this.trigger('card:balance:change', card);

		return {
			uid: card.getUid(),
			transaction: transactionNumber
		}
	}

	async reset(resetUid: string) {

		const card = this.getCard();
		if (!card) {
			throw new NoCardFoundException('No card found.');
		}

		const amount = 0 - card.getBalance();

		// try to write the transaction to card
		this.recoverTransactionIfNeeded(card);
		const transactionNumber = card.applyTransaction(amount);
		const transaction = new Transaction(
			card.getUid(),
			transactionNumber,
			'reset',
			new Date(),
			amount,
			null,
			resetUid
		);

		await this.persist(transaction, card);

		// yay! save that transaction (but don't wait for upload)
		await this.offlineStore.addPendingTransaction(transaction);

		// and refresh the card.
		await this.refreshCard(card);

		this.trigger('card:balance:change', card);

		// also reset all aliases
		card.removeAllOrderTokenAliases();
		await this.saveCardAliases(card);

		return {
			uid: card.getUid(),
			transaction: transactionNumber
		}

	}

	private async persist(transaction:Transaction, card:Card){
		try{
			await card.save();
			this.failedTransactions.delete(card.id!);
			this.transactionStore.persistFailedTransactions(this.failedTransactions);
		}catch (e){
			if(e instanceof NfcWriteException){
				this.failedTransactions.set(card.id!, transaction);
				this.transactionStore.persistFailedTransactions(this.failedTransactions);
				console.log("Writing to card failed, added tx to failed transactions and persisted to localstorage");
			}
			throw e;
		}
	}

	private recoverTransactionIfNeeded(card:Card):void{
		let failedTx = this.failedTransactions.get(card.id!) || null;

		if(failedTx != null && failedTx.transactionId == card.transactionCount){
			failedTx.reverse();
			card.applyTransaction(failedTx.amount);
		}
	}

	/**
	 * @param orderUid
	 * @param amount
	 */
	async spend(orderUid: string, amount: number) {
		const card = this.getCard();
		if (!card) {
			throw new NoCardFoundException('No card found.');
		}

		if (card.isCorrupted()) {
			throw new CorruptedCardException('Card data is corrupt or not linked to this organisation.');
		}

		// discount time!
		const discount = card.discountPercentage;
		if (discount >= 1) {
			amount = 0;
		} else {
			amount = Math.ceil(amount * (1 - (discount / 100)));
		}

		if (amount > 0 && card.balance < amount) {
			throw new InsufficientFundsException('Insufficient funds.');
		}

		this.recoverTransactionIfNeeded(card);

		let transactionId = card.applyTransaction(0 - amount);
		const transaction = new Transaction(
			card.getUid(),
			transactionId,
			'sale',
			new Date(),
			0 - amount,
			orderUid,
			null,
			discount
		);

		await this.persist(transaction, card);

		// yay! save that transaction (but don't wait for upload)
		await this.offlineStore.addPendingTransaction(transaction);
		this.trigger('card:balance:change', card);

		return {
			uid: card.getUid(),
			transaction: transaction.transactionId,
			discount: discount,
			amount: amount
		}

	}

	/**
	 * @param card
	 */
	async saveCardAliases(card: Card) {

		const response = await this.axios({
			method: 'put',
			url: 'cards/' + card.id,
			data: {
				orderTokenAliases: card.orderTokenAliases
			}
		});
	}

	/**
	 * @param card
	 */
	async getTransactions(cardId: string | null = null) {
		if (cardId === null) {
			return await this.transactionStore.getAllTransactions();
		} else {
			return await this.transactionStore.getTransactions(cardId);
		}
	}

	/**
	 * If set to TRUE, card refresh is skipped when internet connection is bad.
	 * @param skipRefreshOnBadInternetConnection
	 */
	public setSkipRefreshWhenBadInternetConnection(skipRefreshOnBadInternetConnection = false) {
		this.skipRefreshWhenBadInternetConnection = skipRefreshOnBadInternetConnection;
		return this;
	}

	/**
	 * Check if provided card is corrupt and trigger event if so.
	 * @param card
	 */
	private async checkIfCardIsCorrupt(card: Card) {

		// Already marked corrupt? Don't do anything.
		if (card.isCorrupted()) {
			return;
		}

		// check if our last known transaction still matches
		this.logger.log(card.getUid(), 'Comparing last seen transaction id');
		const lastSeenSyncId = this.offlineStore.getLastKnownSyncId(card.getUid());
		if (lastSeenSyncId > card.transactionCount) {

			this.logger.log(card.getUid(), 'Last known transaction count is higher than current transaction count. Card is corrupt.');

			card.setCorrupted();
			return;
		}

		this.logger.log(card.getUid(), 'Setting last seen transaction id');
		this.offlineStore.setLastKnownSyncId(card.getUid(), card.transactionCount);

	}
}
