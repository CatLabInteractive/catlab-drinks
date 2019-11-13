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

import {VisibleAmount} from "../tools/VisibleAmount";

export class Transaction {

    public localStorageKey: string = '';

    public uploaded = false;

    public order: any = null;

    public topup: any = null;

    public card: any = null;

    public id: number | null = null;

    static unserialize(data: any) {
        let date = null;

        if (data.timestamp) {
            date = new Date();
            date.setTime(data.timestamp);
        }

        return new Transaction(
            data.cardUid,
            data.transactionId,
            data.type,
            date,
            data.amount,
            data.orderUid,
            data.topupUid
        )
    }

    /**
     * @param cardUid
     * @param transactionId
     * @param type
     * @param date
     * @param amount
     * @param orderUid
     * @param topupUid
     * @param discount
     */
    constructor(
        public cardUid: string,
        public transactionId: number,
        public type: string,
        public date: Date | null,
        public amount: number,
        public orderUid: string | null = null,
        public topupUid: string | null = null,
        public discount: number = 0
    ) {

    }

    /**
     * @return string
     */
    public getVisibleAmount() {
        return VisibleAmount.toVisible(this.amount);
    }

    /**
     *
     */
    serialize() {
        return {
            cardUid: this.cardUid,
            transactionId: this.transactionId,
            type: this.type,
            timestamp: this.date ? this.date.getTime() : null,
            amount: this.amount,
            orderUid: this.orderUid,
            topupUid: this.topupUid
        }
    }
}
