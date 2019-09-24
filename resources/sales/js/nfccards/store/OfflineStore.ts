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

export class OfflineStore {

    private pendingTransactions: Transaction[] = [];

    private lastKnownSyncIds: { [ key: string ]: number } = {};

    public addPendingTransaction(transaction: Transaction) {
        this.pendingTransactions.push(transaction);
    }

    public getPendingTransactions() {
        let out = this.pendingTransactions;
        this.pendingTransactions = [];

        return out;
    }

    public getLastKnownSyncId(card: string) {
        if (typeof(this.lastKnownSyncIds[card]) === 'undefined') {
            return 0;
        }
        return this.lastKnownSyncIds[card];
    }

    public setLastKnownSyncId(card: string, syncId: number) {
        this.lastKnownSyncIds[card] = syncId;
    }
}
