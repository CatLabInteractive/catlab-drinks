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

export interface EventListener {
    unbind: () => void
}

export abstract class Eventable {

    private events: any = {};

    private index = 0;

    /**
     * @param event
     * @param callback
     */
    public on(event: string, callback: (...parameters: any) => void): EventListener
    {
        if (typeof(this.events[event]) === 'undefined') {
            this.events[event] = [];
        }

        const id = this.index ++;
        this.events[event].push({
            id: id,
            callback: callback
        });

        return {
            unbind: () => {
                for (let i = 0; i < this.events[event].length; i ++) {
                    if (this.events[event][i].id === id) {
                        this.events[event].splice(i, 1);
                        return;
                    }
                }
                throw new Error('Failed unbinding event: does not exist.');
            }
        };
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
            (event: { callback: ()  => void }) => {
                event.callback.apply(this, parameters);
            }
        );
    }
}
