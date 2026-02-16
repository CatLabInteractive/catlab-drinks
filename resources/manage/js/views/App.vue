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

	<div>

		<b-navbar toggleable="lg">
			<b-navbar-brand href="#">CatLab Drinks</b-navbar-brand>

			<b-navbar-toggle target="nav_collapse" />

			<b-collapse is-nav id="nav_collapse">
				<b-navbar-nav>

					<b-nav-item :to="{ name: 'events' }">Events</b-nav-item>

				</b-navbar-nav>

				<!-- Right aligned nav items -->
				<b-navbar-nav class="ml-auto">

					<b-navbar-nav>

						<b-nav-item :to="{ name: 'devices' }">Devices</b-nav-item>
						<b-nav-item :to="{ name: 'organisationSettings' }">Settings</b-nav-item>

						<li class="nav-item">
							<logout-link />
						</li>

					</b-navbar-nav>
				</b-navbar-nav>
			</b-collapse>
		</b-navbar>

		<router-view></router-view>

	</div>

</template>
<script>

	import LogoutLink from '../components/LogoutLink.vue';

	export default {

		components: {
			'logout-link': LogoutLink,
		},

		data() {
			return {
				kioskMode: false
			}
		},

		unmounted() {
			this.eventListeners.forEach(e => e.unbind());
		},

		mounted() {
			this.eventListeners = [];
		}
	}

</script>
