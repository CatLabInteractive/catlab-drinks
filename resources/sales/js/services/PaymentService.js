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
import {CorruptedCard} from "../nfccards/exceptions/CorruptedCard";

export class PaymentService extends Eventable {

    constructor() {
        super();
    }

    setCardService(cardService) {
        this.cardService = cardService;

        this.currentTransaction = null;

        // list for card connect event so that we can show a spinner
        this.cardService.on('card:connect', (card) => {
            if (this.currentTransaction) {
                this.onCardConnect(card, this.currentTransaction);
            }
        });

        this.cardService.on('card:loaded', (card) => {
            if (this.currentTransaction) {
                this.handleTransaction(card, this.currentTransaction);
            }
        });
    }

    async order(order) {

        const price = Math.ceil(order.price * 100);

        return new Promise(
            (resolve, reject) => {
                this.currentTransaction = {
                    price: price,
                    orderId: order.uid,
                    error: null,
                    loading: false,
                    resolve: resolve,
                    reject: reject
                };

                this.trigger('transaction:start', this.currentTransaction);

                if (this.cardService) {
                    // Do we have a card?
                    const card = this.cardService.getCard();
                    if (card) {
                        this.handleTransaction(card, this.currentTransaction)
                    }
                }
            }
        )
    }

    async onCardConnect(card, transaction) {
        this.currentTransaction.loading = true;
        this.trigger('transaction:change', transaction);
    }

    async handleTransaction(card, transaction) {

        // let's spend some money

        // set transaction state to 'loading' so that we can display a nice little spinner
        this.currentTransaction.loading = true;

        try {

            const out = await this.cardService.spend(transaction.orderId, transaction.price);
            out.paymentType = 'card';

            this.currentTransaction = null;

            transaction.resolve(out);
            this.trigger('transaction:done');

        } catch (e) {
            if (e instanceof InsufficientFunds) {
                transaction.error = 'Insufficient funds.';
            } else if (e instanceof NoCardFound) {
                transaction.error = 'No card found, please represent card.';
            } else if (e instanceof NfcWriteException) {
                transaction.error = 'Nfc error, please scan again.';
            } else if (e instanceof CorruptedCard) {
                transaction.error = 'The card is corrupt or does not belong to this organisation. Please contact support.';
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

    async cash() {

        if (!this.currentTransaction) {
            return;
        }

        this.currentTransaction.resolve({ paymentType: 'cash' });

        this.currentTransaction = null;
        this.trigger('transaction:done');
    }
}
