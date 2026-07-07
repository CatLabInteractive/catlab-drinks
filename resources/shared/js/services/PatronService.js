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

    /**
     * Atomically settle all unpaid orders of a patron.
     * @param patronId
     * @param paymentType e.g. 'cash', 'vouchers', 'nfc'
     * @param discount Discount percentage applied by the payment (0-100)
     */
    settle(patronId, paymentType = null, discount = 0) {
        return this.execute('post', 'patrons/' + patronId + '/settle', {
            payment_type: paymentType,
            discount: discount
        });
    }
}
