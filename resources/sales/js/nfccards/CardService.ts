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
import {InsufficientFunds} from "./exceptions/InsufficientFunds";
import {NoCardFound} from "./exceptions/NoCardFound";
import {Transaction} from "./models/Transaction";

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
    constructor(
        axios: any,
        organisationId: string,
        nfcService: string, // http://192.168.1.194:3000
        nfcPassword: string
    ) {
        super();

        this.offlineStore = new OfflineStore();
        this.transactionStore = new TransactionStore(axios, organisationId, this.offlineStore);
        this.logger = new Logger();

        this.nfcReader = new NfcReader(this.offlineStore, this.logger);
        this.nfcReader.connect(nfcService, nfcPassword);

        // events
        this.nfcReader.on('card:connect', (card: Card) => {
            this.trigger('card:connect', card);
        });

        this.nfcReader.on('card:disconnect', (card: Card) => {
            this.currentCard = null;
            this.trigger('card:disconnect', card);
        });

        this.nfcReader.on('card:loaded', async (card: Card) => {

            // check if there are any transactions that still need to be processed

            await this.refreshCard(card);

            this.currentCard = card;

            this.trigger('card:loaded', card);
            this.trigger('card:balance:change', card);
        });
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
    async refreshCard(card: Card, forceWrite = false)
    {
        const serverCard = await this.transactionStore.getCard(card.getUid());
        if (serverCard) {

            // check for pending transactions
            const pendingTransactions = serverCard.pendingTransactions.items;
            if (pendingTransactions.length > 0) {

                // apply each transaction to the card
                try {
                    pendingTransactions.forEach(
                        (transaction: any) => {
                            transaction.card_transaction = card.applyTransaction(transaction.value);
                            delete transaction.card_date;
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
            await this.transactionStore.uploadCardData(serverCard.id, card);
        }
    }

    /**
     * This method is potentially dangerous.
     * This will rebuild the card data based on the transactions that are known
     * to the server. Transactions that have not been uploaded will thus not be
     * taken into account. Sales might get lost and the credit provided here
     * might be too high.
     * @param card
     */
    async rebuild(card: Card){

        if (!this.transactionStore.isOnline()) {
            throw new OfflineException('rebuild only works with an active internet connection');
        }

        // load the server card
        const serverCard = await this.transactionStore.getCard(card.getUid());

        // reset the last known sync id.
        this.offlineStore.setLastKnownSyncId(card.getUid(), 0);

        // mark all online transactions as 'pending'
        await this.transactionStore.setAllTransactionsPending(serverCard.id);

        // format the card
        card.balance = 0;
        card.transactionCount = 0;
        card.previousTransactions = [ 0, 0, 0, 0, 0 ];
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
        return this.currentCard;
    }

    async topup(topupUid: string, amount: number) {
        const card = this.currentCard;
        if (!card) {
            throw new NoCardFound('No card found.');
        }

        const transaction = new Transaction(
            card.getUid(),
            new Date(),
            amount,
            null,
            topupUid
        );

        // try to write the transaction to card
        card.balance += amount;
        await card.save();

        // yay! save that transaction (but don't wait for upload)
        this.offlineStore.addPendingTransaction(transaction);
        this.trigger('card:balance:change', card);
    }

    async spend(orderUid: string, amount: number) {

        const card = this.currentCard;
        if (!card) {
            throw new NoCardFound('No card found.');
        }

        if (card.balance < amount) {
            throw new InsufficientFunds('Insufficient funds.');
        }

        const transaction = new Transaction(
            card.getUid(),
            new Date(),
            amount,
            orderUid
        );

        // try to write the transaction to card
        card.balance -= amount;
        await card.save();

        // yay! save that transaction (but don't wait for upload)
        this.offlineStore.addPendingTransaction(transaction);
        this.trigger('card:balance:change', card);
    }

    /**
     * @param card
     */
    async getTransactions(card: any) {



    }

}