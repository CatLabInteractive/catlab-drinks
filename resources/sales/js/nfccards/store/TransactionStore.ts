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

import {OfflineStore} from "./OfflineStore";
import {Card} from "../models/Card";

export class TransactionStore {

    private transactionIdCursor: string = '';

    constructor(
        private axios: any,
        private organisationId: string,
        private offlineStore: OfflineStore
    ) {
        setTimeout(
            () => {
                setInterval(
                    () => {
                        this.refresh();
                    },
                    5000
                );
            },
            2500
        );
        this.refresh();
    }

    /**
     * Do we have an active internet connection?
     */
    public isOnline() {
        return true;
    }

    public getCard(card: string): Promise<any> {
        if (!this.isOnline()) {
            return Promise.resolve(null);
        }

        return new Promise(
            (resolve, reject) => {

                this.axios.get('organisations/' + this.organisationId + '/card-from-uid/' + card + '?markClientDate=1')
                    .then(
                        (response: any) => {
                            resolve(response.data);
                        }
                    )

            }
        );
    }

    /**
     * Mark transactions back to pending.
     * @param transactions
     */
    public async markTransactionsAsPending(transactions: any[])
    {
        transactions.forEach(
            (transaction: any) => {
                transaction.card_date = null;
            }
        );

        return this.updateTransactions(transactions);
    }

    /**
     * @param transactions
     */
    public async updateTransactions(transactions: any[]) {
        const promises: any[] = [];
        transactions.forEach(
            (transaction: any) => {
                promises.push(this.updateTransaction(transaction));
            }
        );

        await Promise.all(promises);
    }

    public async updateTransaction(transaction: any) {

        await new Promise(
            (resolve, reject) => {

                this.axios({
                    method: 'put',
                    url: 'transactions/' + transaction.id,
                    data: transaction
                })
                    .then(
                        (response: any) => {
                            resolve();
                        }
                    )

            }
        );

    }

    public async uploadCardData(cardId: string, card: Card)
    {
        await new Promise(
            (resolve, reject) => {

                this.axios({
                    method: 'post',
                    url: 'cards/' + cardId + '/card-data',
                    data: card.getServerData()
                })
                    .then(
                        (response: any) => {
                            resolve();
                        }
                    )

            }
        );
    }

    public getPendingOfflineTransactions() {

    }

    public refresh() {

        this.uploadPendingTransactions();
        this.refreshCardTransactionCounts();

    }

    private refreshCardTransactionCounts() {
        return this.axios({
            method: 'get',
            url: 'organisations/' + this.organisationId + '/cards?records=1000&fields=uid,transactions,updated_at&sort=updated_at&after=' + this.transactionIdCursor
        }).then(
            (response: any) => {

                const data = response.data;
                if (
                    data.meta &&
                    data.meta.pagination &&
                    data.meta.pagination.cursors
                ) {
                    this.transactionIdCursor = data.meta.pagination.cursors.after;
                }

                // update transactions
                data.items.forEach(
                    (card: any) => {
                        this.offlineStore.setLastKnownSyncId(card.uid, card.transactions);
                    }
                )

            }
        );
    }

    private uploadPendingTransactions() {

        const pendingTransactions = this.offlineStore.getPendingTransactions();

    }
}
