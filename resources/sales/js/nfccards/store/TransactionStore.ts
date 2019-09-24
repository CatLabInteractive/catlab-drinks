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

import {Transaction} from "../models/Transaction";
import {OfflineStore} from "./OfflineStore";

export class TransactionStore {

    private offlineStore: OfflineStore;

    private transactionIdCursor: string = '';

    constructor(
        private axios: any
    ) {
        this.offlineStore = new OfflineStore();

        setInterval(
            () => {
                this.refresh();
            },
            5000
        );
    }

    /**
     * Do we have an active internet connection?
     */
    public isOnline() {
        return true;
    }

    /**
     * @param card
     * @return int
     */
    public getLastKnownTransactionId(card: string) {
        return 0;
    }

    /**
     *
     */
    public getPendingOnlineTransactions() {
        if (!this.isOnline()) {
            return Promise.resolve([]);
        }

        return new Promise(
            (resolve, reject) => {

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
            url: 'organisations/1/cards?records=1000&fields=uid,transactions,updated_at&sort=updated_at&after=' + this.transactionIdCursor
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
