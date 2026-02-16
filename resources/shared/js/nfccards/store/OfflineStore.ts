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
import * as localForage from "localforage";

/**
 *
 */
export class OfflineStore {

    /**
     *
     */
    private lastKnownSyncIds: { [ key: string ]: number } = {};

    private localForagePrefix = '';

    constructor(
        private organisationId: string
    ) {
        this.localForagePrefix = 'org_' + this.organisationId + '_';

        localForage.getItem(this.localForagePrefix + '_lastKnownSyncIds', (err, value: { [ key: string ]: number } | null) => {
            if (err) {
                alert('Failed loading lastKnownSyncIds');
                console.error(err);
                return;
            }
            if (value) {
                this.lastKnownSyncIds = value;
            }
        });
    }

    /**
     * Add a transaction that is not synced to the api yet.
     * @param transaction
     */
    public addPendingTransaction(transaction: Transaction): Promise<void> {
        return new Promise(
            (resolve, reject) => {
                localForage.setItem(
                    this.localForagePrefix + 'transaction_' + (new Date()).getTime(),
                    transaction.serialize(),
                    function(err, result) {
                        if (err) {
                            reject(err);
                            return;
                        }
                        resolve();
                    }
                );
            }
        );
    }

    /**
     * Get all transactions that have not been synced to the online api
     */
    public async getPendingTransactions(): Promise<Transaction[]> {
        return await new Promise(
            (resolve, reject) => {
                const out: Transaction[] = [];

                localForage.iterate((value, key, iterationNumber) => {
                    if (this.keyStartsWith(key, 'transaction_')) {
                        const transaction = Transaction.unserialize(value);
                        transaction.localStorageKey = key;
                        out.push(transaction);
                    }
                }).then(
                    () => {
                        resolve(out);
                    }
                );
            }
        );
    }

    /**
     * @param key
     * @param check
     */
    private keyStartsWith(key: string, check: string) {
        const fullCheck = this.localForagePrefix + check;
        return key.substr(0, fullCheck.length) === fullCheck
    }

    /**
     * Get the last known transaction count of a specific card.
     * @param card
     */
    public getLastKnownSyncId(card: string) {
        if (typeof(this.lastKnownSyncIds[card]) === 'undefined') {
            return 0;
        }
        return this.lastKnownSyncIds[card];
    }

    /**
     * Set the last known transaction count
     * @param card
     * @param syncId
     */
    public async setLastKnownSyncId(card: string, syncId: number) {
        this.lastKnownSyncIds[card] = syncId;

        // store in localstorage
        await localForage.setItem(this.localForagePrefix + 'lastKnownSyncIds', this.lastKnownSyncIds);
    }

    /**
     * @param cards
     */
    public async setLastKnownSyncIds(cards: { uid: string, transactions: number }[])
    {
        cards.forEach(card => {
            this.lastKnownSyncIds[card.uid] = card.transactions;
        });

        await localForage.setItem(this.localForagePrefix + 'lastKnownSyncIds', this.lastKnownSyncIds);
    }

    /**
     * Set the data that will be written to an nfc card.
     * This is used to recover from failed writes.
     * @param uid
     * @param byteArray
     */
    public async setCardState(uid: string, byteArray: string) {

        await localForage.setItem(this.localForagePrefix + 'card_state_' + uid, {
            date: new Date(),
            body: byteArray
        });
    }

    /**
     * Look for a serialized card state that is still valid.
     * @param uid
     * @return number[]
     */
    public async getCardState(uid: string) {

        const cardState: any = await localForage.getItem(this.localForagePrefix + 'card_state_' + uid);
        if (!cardState) {
            return null;
        }

        if (cardState.date.getTime() > ((new Date()).getTime() - 60 * 15 * 1000)) {
            return cardState.body;
        }

        return null;
    }

    public removePendingTransactions(pendingTransactions: Transaction[]) {
        pendingTransactions.forEach(
            (transaction: Transaction) => {
                localForage.removeItem(transaction.localStorageKey);
            }
        );
    }
}
