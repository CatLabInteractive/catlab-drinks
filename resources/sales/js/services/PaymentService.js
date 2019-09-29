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

import {Eventable} from "../utils/Eventable";
import {Card} from "../nfccards/models/Card";
import {NoCardFound} from "../nfccards/exceptions/NoCardFound";
import {NfcWriteException} from "../nfccards/exceptions/NfcWriteException";
import {InsufficientFunds} from "../nfccards/exceptions/InsufficientFunds";

export class PaymentService extends Eventable {

    constructor() {
        super();
    }

    setCardService(cardService) {
        this.cardService = cardService;

        this.currentTransaction = null;

        this.cardService.on('card:loaded', (card) => {
            if (this.currentTransaction) {
                this.handleTransaction(card, this.currentTransaction);
            }
        });
    }

    async order(order) {

        const price = Math.ceil(order.price * 100);
        if (!this.cardService) {
            // no card server, always correct.
            return Promise.resolve(true);
        }

        return new Promise(
            (resolve, reject) => {
                this.currentTransaction = {
                    price: price,
                    orderId: order.uid,
                    error: null,
                    resolve: resolve,
                    reject: reject
                };

                this.trigger('transaction:start', this.currentTransaction);

                // Do we have a card?
                const card = this.cardService.getCard();
                if (card) {
                    this.handleTransaction(card, this.currentTransaction)
                }
            }
        )
    }

    async handleTransaction(card, transaction) {

        // let's spend some money
        try {
            await this.cardService.spend(transaction.orderId, transaction.price);
            this.currentTransaction = null;

            transaction.resolve(true);
            this.trigger('transaction:done');
        } catch (e) {
            if (e instanceof InsufficientFunds) {
                transaction.error = 'Insufficient funds.';
            } else if (e instanceof NoCardFound) {
                transaction.error = 'No card found, please represent card.';
            } else if (e instanceof NfcWriteException) {
                transaction.error =' Nfc error, please scan again.';
            }
        }

        this.trigger('transaction:change', transaction);
    }

    async cancel() {

        if (!this.currentTransaction) {
            return;
        }

        // close the state
        this.currentTransaction.reject(new Error('Transaction cancelled manually.'));

        this.currentTransaction = null;
        this.trigger('transaction:done');
    }
}
