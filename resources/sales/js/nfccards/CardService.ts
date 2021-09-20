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

    private recoverTransacations: Map<number, Transaction>;

    public hasCardReader: boolean = false;

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

        this.nfcReader = new NfcReader(this.offlineStore, this.logger);
        this.recoverTransacations = new Map();
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
            this.currentCard = null;
            this.isCardLoaded = false;
            this.trigger('card:disconnect', card);
        });

        this.nfcReader.on('card:loaded', async (card: Card) => {

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

                card.setReady();
                this.trigger('card:loaded', card);
                this.trigger('card:balance:change', card);
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

        const serverCard = await this.transactionStore.getCard(card.getUid(), true);
        if (serverCard) {

            // set interla id
            card.id = serverCard.id;
            card.orderTokenAliases = serverCard.orderTokenAliases;

            // check for pending transactions
            const pendingTransactions = serverCard.pendingTransactions.items;
            if (pendingTransactions.length > 0) {

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
                    await card.save();

                    await this.transactionStore.updateTransactions(pendingTransactions);

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
            await this.uploadCardData(card);
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
        card.previousTransactions = [{'id':-1, 'amount':0},{'id':-1, 'amount':0},{'id':-1, 'amount':0},{'id':-1, 'amount':0},{'id':-1, 'amount':0} ];
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
        const transactionNumber = card.applyTransaction(amount);
        await card.save();

        const transaction = new Transaction(
            card.getUid(),
            transactionNumber,
            'topup',
            new Date(),
            amount,
            null,
            topupUid
        );

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

    private getRecoverTransaction(card:Card): Transaction | undefined{
        let lastTx = card.getPreviousTransactions()
                         .filter(x => x.id > -1)
                         .reduce((one,other) => one.id - other.id > 0?one:other, null);

        if(lastTx !== null){
            let failedTx = this.recoverTransacations.get(card.id!);
            if(failedTx !== undefined && lastTx.id === failedTx.id){
                failedTx.amount = failedTx.reverse()
                failedTx.id = card.applyTransaction(failedTx.amount);
                return failedTx;
            }
        }
        return undefined;
    }

    /**
     * @param orderUid
     * @param amount
     */
    async spend(orderUid: string, amount: number) {

        //console.log('CardService: handling order ' + orderUid);
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

        //TODO @jdb cope with the fact that this might fail as well and cause a chain reaction
        let recoverTransaction = this.getRecoverTransaction(card)

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

        try{
            await card.save();
            this.recoverTransacations.delete(card.id!);
        }catch (e){
            if(e instanceof NfcWriteException){
                this.recoverTransacations.set(card.id!, transaction);
            }
            throw e;
        }

        // yay! save that transaction (but don't wait for upload)
        this.offlineStore.addPendingTransaction(transaction);
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

}
