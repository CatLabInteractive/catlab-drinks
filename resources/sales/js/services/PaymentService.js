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
import {NoCardFoundException} from "../nfccards/exceptions/NoCardFoundException";
import {NfcWriteException} from "../nfccards/exceptions/NfcWriteException";
import {InsufficientFundsException} from "../nfccards/exceptions/InsufficientFundsException";
import {CorruptedCardException} from "../nfccards/exceptions/CorruptedCardException";
import {TransactionCancelledException} from "../nfccards/exceptions/TransactionCancelledException";

/**
 *
 */
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

    /**
     * @param order
     * @param acceptCurrentCard
     * @returns any
     */
    async order(order, acceptCurrentCard = true) {

        // handle the actual payment
        console.log(order);
        let paymentData = await this.handleOrder(order, true);

        // mark order as paid
        order.paid = true;

        // apply any discount that might have been assigned based on card data.
        // warning: discount is a percentage (int) between 0 and 100
        if (typeof(paymentData.discount) !== 'undefined') {
            order.discount = paymentData.discount;
        }

        // update the total order price
        if (typeof(paymentData.amount) !== 'undefined') {
            order.price = paymentData.amount / 100;
        }

        // update the price of all items in the order
        let discountFactor = 1 - (paymentData.discount / 100);

        // apply the discount to all order items too
        order.order.items.forEach(
            (orderItem) => {
                orderItem.price *= discountFactor;
            }
        );

        return paymentData;
    }

    /**
     * Helper method to keep the order code more clear.
     * @param order
     * @param acceptCurrentCard
     * @returns {Promise<void>}
     */
    async handleOrder(order, acceptCurrentCard = true) {
        const price = Math.ceil(order.price * 100);

        return new Promise(
            (resolve, reject) => {
                this.currentTransaction = {
                    price: price,
                    orderId: order.uid,
                    error: null,
                    loading: false,
                    discount: 0,
                    resolve: resolve,
                    reject: reject
                };

                this.trigger('transaction:start', this.currentTransaction);

                if (this.cardService && acceptCurrentCard) {
                    const card = this.cardService.getCard();
                    if (card) {
                        this.handleTransaction(card, this.currentTransaction)
                    }
                }
            }
        )
    }

    /**
     * @param card
     * @param transaction
     * @returns {Promise<void>}
     */
    async onCardConnect(card, transaction) {
        this.currentTransaction.loading = true;
        this.trigger('transaction:change', transaction);
    }

    /**
     * @param card
     * @param transaction
     * @returns {Promise<void>}
     */
    async handleTransaction(card, transaction) {

        // let's spend some money

        // set transaction state to 'loading' so that we can display a nice little spinner
        this.currentTransaction.loading = true;

        try {

            let price = transaction.price;

            const out = await this.cardService.spend(transaction.orderId, price);
            this.currentTransaction.loading = false;
            out.paymentType = 'card';

            this.currentTransaction = null;

            transaction.resolve(out);
            this.trigger('transaction:done');

        } catch (e) {
            this.currentTransaction.loading = false;
            if (e instanceof InsufficientFundsException) {
                transaction.error = 'Insufficient funds.';
            } else if (e instanceof NoCardFoundException) {
                transaction.error = 'No card found, please represent card.';
            } else if (e instanceof NfcWriteException) {
                transaction.error = 'Nfc error, please scan again.';
            } else if (e instanceof CorruptedCardException) {
                transaction.error = 'The card is corrupt or does not belong to this organisation. Please contact support.';
            }
        }

        this.trigger('transaction:change', transaction);
    }

    /**
     * @returns {Promise<void>}
     */
    async cancel() {

        if (!this.currentTransaction) {
            return;
        }

        // close the state
        this.currentTransaction.reject(new TransactionCancelledException('Transaction cancelled manually.'));

        this.currentTransaction = null;
        this.trigger('transaction:done');
    }

    /**
     * @returns {Promise<void>}
     */
    async cash() {

        if (!this.currentTransaction) {
            return;
        }

        this.currentTransaction.resolve({
            paymentType: 'cash',
            discount: 0
        });

        this.currentTransaction = null;
        this.trigger('transaction:done');
    }
}
