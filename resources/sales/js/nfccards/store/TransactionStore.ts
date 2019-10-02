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
import {Transaction} from "../models/Transaction";

export class TransactionStore {

    private transactionIdCursor: string = '';

    constructor(
        private axios: any,
        private organisationId: string,
        private offlineStore: OfflineStore
    ) {

        const refresh = async() => {
            await this.refresh();
            setTimeout(() => {
                refresh();
            }, 5000)
        };

        refresh();
    }

    /**
     * Do we have an active internet connection?
     */
    public isOnline() {
        return true;
    }

    /**
     * @param card
     */
    public getCard(card: string): Promise<any> {
        if (!this.isOnline()) {
            return Promise.resolve(null);
        }

        return new Promise(
            (resolve, reject) => {

                this.axios.get('organisations/' + this.organisationId + '/card-from-uid/' + card + '?markSynced=1')
                    .then(
                        (response: any) => {
                            resolve(response.data);
                        }
                    )

            }
        );
    }

    /**
     * @param serverCard
     */
    public setAllTransactionsPending(serverCard: string): Promise<any> {
        return new Promise(
            (resolve, reject) => {

                this.axios.post('cards/' + serverCard + '/reset-transactions')
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
                transaction.has_synced = false;
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

    /**
     * @param transaction
     */
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

    /**
     * @param cardId
     * @param card
     */
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

    /**
     *
     */
    public async refresh() {

        console.log('Refreshing local data');
        try {
            await this.uploadPendingTransactions();
        } catch (err) {
            console.error(err);
        }

        try {
            await this.refreshCardTransactionCounts();
        } catch (err) {
            console.error(err);
        }
    }

    /**
     *
     */
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

    /**
     * Upload all transactions that we don't have yet.
     */
    private async uploadPendingTransactions() {

        const pendingTransactions = await this.offlineStore.getPendingTransactions();
        if (pendingTransactions.length > 0) {
            const list: any = [];

            pendingTransactions.forEach(
                (item: Transaction) => {

                    let type = 'unknown';
                    if (item.orderUid !== null) {
                        type = 'sale';
                    } else if (item.topupUid !== null) {
                        type = 'topup'
                    }

                    list.push({
                        card: item.cardUid,
                        value: item.amount,
                        type: type,
                        card_transaction: item.transactionId,
                        card_date: this.toApiDate(item.date),
                        order_uid: item.orderUid,
                        topup_uid: item.topupUid
                    });
                }
            );

            const body = {
                items: list
            };

            await new Promise(
                (resolve, reject) => {

                    this.axios({
                        method: 'post',
                        url: 'organisations/' + this.organisationId + '/merge-transactions',
                        data: body
                    })
                        .then(
                            (response: any) => {
                                this.offlineStore.removePendingTransactions(pendingTransactions);
                                resolve();
                            }
                        )

                }
            );
        }

    }

    public toApiDate(date: Date | null) {
        if (date) {
            return date.toISOString().split('.')[0]+"Z";
        }
        return null;
    }

    async getTransactions(card: Card) {

        const transactions = await this.axios.get('cards/' + card.id + '/transactions?records=1000&sort=!card_transaction');

        const out: Transaction[] = [];
        transactions.data.items.forEach(
            (item: any) => {
                let date = null;
                if (item.card_date) {
                    date = new Date(Date.parse(item.card_date));
                }

                const transaction = new Transaction(
                    card.getUid(),
                    item.card_transaction,
                    item.type,
                    date,
                    item.value,
                    item.orderUid,
                    item.topupUid
                );
                out.push(transaction);
            }
        );

        return out;
    }
}
