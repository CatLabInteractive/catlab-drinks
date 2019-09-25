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

export abstract class Eventable {

    private events: any = {};

    private index = 0;

    /**
     * @param event
     * @param callback
     */
    public on(event: string, callback: (...parameters: any) => void)
    {
        if (typeof(this.events[event]) === 'undefined') {
            this.events[event] = [];
        }

        const index = this.index ++;

        this.events[event].push(callback);
        return index;
    }

    /**
     * @param event
     * @param parameters
     */
    public trigger(event: string, ...parameters: any)
    {
        if (typeof(this.events[event]) === 'undefined') {
            return;
        }

        this.events[event].forEach(
            (callback: () => void) => {
                callback.apply(this, parameters);
            }
        );
    }
}
