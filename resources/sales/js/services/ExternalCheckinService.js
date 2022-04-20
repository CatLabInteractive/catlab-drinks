export class ExternalCheckinService {

    async checkin(event, attendee, card) {

        if (!event.checkin_url) {
            return;
        }

        const checkinUrl = event.checkin_url;

        const client = window.axios.create({
            json: true
        });

        await client({
            method: 'post',
            url: checkinUrl,
            data: {
                uid: card.uid,
                name: attendee.name,
                email: attendee.email
            }
        }).then(
            (response) => {
                return response.data;
            }
        )
    }

}
