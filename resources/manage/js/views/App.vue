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

					<b-nav-item :to="{ name: 'events' }">{{ $t('Events') }}</b-nav-item>
					<b-nav-item :to="{ name: 'devices' }">{{ $t('Points of sale') }}</b-nav-item>

				</b-navbar-nav>

				<!-- Right aligned nav items -->
				<b-navbar-nav class="ml-auto">

					<b-navbar-nav>

						<b-nav-item-dropdown :text="$t('Settings')" right>
							<b-dropdown-item :to="{ name: 'settings' }">{{ $t('Organisation Settings') }}</b-dropdown-item>
							<b-dropdown-item :to="{ name: 'publicKeys' }">{{ $t('Public Keys') }}</b-dropdown-item>
						</b-nav-item-dropdown>

						<language-toggle />

						<li class="nav-item">
							<logout-link />
						</li>

					</b-navbar-nav>
				</b-navbar-nav>
			</b-collapse>
		</b-navbar>

		<b-alert v-if="testMode" show variant="warning" class="mb-0 text-center rounded-0">
			{{ $t('You are using this shared instance in testing mode. Feel free to try things out, but for production events please set up your own instance.') }}
			<a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance" target="_blank" rel="noopener" class="alert-link">{{ $t('How to set up your own instance') }}</a>
		</b-alert>

		<router-view></router-view>

	</div>

</template>
<script>

	import LogoutLink from '../components/LogoutLink.vue';
	import LanguageToggle from '../../../shared/js/components/LanguageToggle.vue';

	export default {

		components: {
			'logout-link': LogoutLink,
			'language-toggle': LanguageToggle,
		},

		data() {
			return {
				kioskMode: false,
				testMode: !!window.ORGANISATION_TEST_MODE
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
