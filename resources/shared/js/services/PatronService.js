import {AbstractService} from './AbstractService';

export class PatronService extends AbstractService {

    /**
     * @param eventId
     */
    constructor(eventId) {
        super();

        this.eventId = eventId;
        this.indexUrl = 'events/' + eventId + '/patrons';
        this.entityUrl = 'patrons';
    }
}
