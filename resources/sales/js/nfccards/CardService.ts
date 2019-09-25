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
    constructor(axios: any) {
        super();

        this.transactionStore = new TransactionStore(axios);

        this.nfcReader = new NfcReader();
        //this.nfcReader.connect('http://localhost:3000')
        this.nfcReader.connect('http://192.168.1.194:3000')

        // events
        this.nfcReader.on('card:connect', (card: Card) => {
            this.trigger('card:connect', card);
        });

        this.nfcReader.on('card:disconnect', () => {
            this.trigger('card:disconnect');
        })
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
