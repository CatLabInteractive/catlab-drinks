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

import {AbstractService} from './AbstractService';
import $ from "jquery";

export class EventService extends AbstractService {

    /**
     * @param organisationId
     */
    constructor(organisationId) {
        super();

        this.entityUrl = 'events';
        this.indexUrl = 'organisations/' + organisationId + '/events';
    }

    getAttendees(eventId) {
        return this.execute('get', 'events/' + eventId + '/attendees?records=1000');
    }

    createAttendee(eventId, attendee) {
        return this.execute('post', 'events/' + eventId + '/attendees', attendee);
    }

    updateAttendee(eventId, attendeeId, attendee) {
        return this.execute('put', 'events/' + eventId + '/attendees/' + attendeeId, attendee);
    }

    deleteAttendee(eventId, attendeeId) {
        return this.execute('delete', 'events/' + eventId + '/attendees/' + attendeeId);
    }

    importAttendees(eventId, attendeeInput, parameters ) {

        if (typeof(parameters) === 'undefined') {
            parameters = {};
        }

        return this.execute(
            'put',
            '/' + this.entityUrl + '/' + eventId + "/attendees/import?" + $.param(parameters),
            { attendees: attendeeInput }
        );
    }
}
