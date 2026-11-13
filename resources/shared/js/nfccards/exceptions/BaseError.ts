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

/**
 * Base class of the card exceptions. It is a real Error, so it carries a
 * stack trace, and `instanceof` works for each subclass.
 */
export class BaseError extends Error {
    constructor (
        message: string,
        ...args: any[]
    ) {
        super(message);

        // Babel compiles this class to ES5, where a subclass of Error loses
        // its own prototype. Restore it, or `instanceof` fails.
        Object.setPrototypeOf(this, new.target.prototype);
    }
}
