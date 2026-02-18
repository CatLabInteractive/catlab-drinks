<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>
	<span v-if="event">

		<b-button v-if="!event.is_selling" size="sm" @click="toggleIsSelling()" class="btn-danger">
			{{ $t('Closed') }}
		</b-button>

		<b-button v-if="event.is_selling" size="sm" @click="toggleIsSelling()" class="btn-success">
			{{ $t('Open') }}
		</b-button>

	</span>


</template>

<script>

	import {EventService} from "../services/EventService";

	export default {

		props: [
			'eventId'
		],

		mounted() {
			this.service = new EventService();

			if (this.eventId) {
				this.setEventId(this.eventId);
			}
		},

		beforeDestroy() {
			if (this.interval) {
				clearInterval(this.interval);
			}
		},

		data() {
			return {
				event: this.event
			}
		},

		watch: {

			eventId(newVal, oldVal) {
				if (newVal) {
					this.setEventId(newVal);
				}
			}

		},

		methods: {

			setEventId(eventId) {
				if (this.interval) {
					clearInterval(this.interval);
				}

				this.refresh();
				this.interval = setInterval(
					() => {
						this.refresh();
					},
					5000
				);
			},

			async refresh() {

				this.event = await this.service.get(this.eventId);

			},

			async toggleIsSelling() {

				this.event.is_selling = !this.event.is_selling;
				await this.service.update(this.event.id, this.event);

			}

		}
	}
</script>