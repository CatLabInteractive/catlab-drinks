import {AbstractService} from './AbstractService';

export class TableService extends AbstractService {

    /**
     * @param eventId
     */
    constructor(eventId) {
        super();

        this.eventId = eventId;
        this.indexUrl = 'events/' + eventId + '/tables';
        this.entityUrl = 'tables';
    }

    /**
     * Bulk generate tables
     * @param count
     * @returns {Promise<*>}
     */
    async bulkGenerate(count) {
        return this.execute(
            'post',
            '/' + this.indexUrl + '/generate',
            { count: count }
        );
    }
}
