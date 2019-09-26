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
    private offlineStore: OfflineStore;

    /**
     *
     */
    private logger: Logger;

    /**
     *
     */
    constructor(
        axios: any,
        organisationId: string
    ) {
        super();

        this.offlineStore = new OfflineStore();
        this.transactionStore = new TransactionStore(axios, organisationId, this.offlineStore);
        this.logger = new Logger();

        this.nfcReader = new NfcReader(this.offlineStore, this.logger);
        //this.nfcReader.connect('http://localhost:3000')
        this.nfcReader.connect('http://192.168.1.194:3000');

        // events
        this.nfcReader.on('card:connect', (card: Card) => {
            this.trigger('card:connect', card);
        });

        this.nfcReader.on('card:disconnect', (card: Card) => {
            this.trigger('card:disconnect', card);
        });

        this.nfcReader.on('card:loaded', async (card: Card) => {

            // check if there are any transactions that still need to be processed

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

                }

                // upload current values
                await this.transactionStore.uploadCardData(serverCard.id, card);
            }

            this.trigger('card:loaded', card);
        });
    }

    /**
     * @param password
     */
    setPassword(password: string) {
        this.password = password;
        this.nfcReader.setPassword(password);
        return this;
    }

    getTransactions(card: any) {



    }

}
