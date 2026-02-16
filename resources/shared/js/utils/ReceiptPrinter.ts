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

export class ReceiptPrinter {

    constructor(
        private order: any
    ) {

    }

    public print(): string
    {
        let out = '';
        let totalPrice = 0;

        const numberPadding = 9;
        const namePadding = 30;
        const totalName = 'Total: ';

        this.order.order.items.forEach((item: any) => {

            const itemTotal = item.amount * item.menuItem.price;
            totalPrice += itemTotal;

            out +=
                item.amount.toString().padStart(3) + ' x ' +
                item.menuItem.name.toString().substring(0, namePadding - 2).padEnd(namePadding) +
                item.menuItem.price.toFixed(2).padStart(numberPadding) +
                itemTotal.toFixed(2).padStart(numberPadding)
                + '\n';
        });

        out += '\n';
        out += totalName + totalPrice.toFixed(2).padStart(-totalName.length + 3 + 3 + 30 + numberPadding + numberPadding);

        return out;
    }
}
