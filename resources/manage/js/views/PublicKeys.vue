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

	<b-container fluid>

		<h1>{{ $t('Public Keys') }}</h1>

		<p class="text-muted">
			{{ $t('This page shows all public keys for POS devices in your organisation, including keys from deleted devices. Each public key is used to verify NFC card signatures.') }}
		</p>

		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<b-row>
			<b-col>
				<b-table striped hover :items="items" :fields="fields" v-if="loaded">

					<template v-slot:cell(device)="row">
						{{ row.item.name }}
						<span v-if="row.item.deleted_at" class="badge badge-danger ml-1">{{ $t('Deleted') }}</span>
					</template>

					<template v-slot:cell(public_key)="row">
						<code class="small" style="word-break: break-all;">{{ truncateKey(row.item.public_key) }}</code>
					</template>

					<template v-slot:cell(status)="row">
						<span v-if="row.item.approved_at" class="badge badge-success">
							✅ {{ $t('Approved') }}
						</span>
						<span v-else class="badge badge-warning text-dark">
							⏳ {{ $t('Pending Approval') }}
						</span>
					</template>

					<template v-slot:cell(signed_cards)="row">
						<a href="#" @click.prevent="showSignedCards(row.item)" v-if="row.item.signed_cards_count > 0">
							{{ row.item.signed_cards_count }}
						</a>
						<span v-else>0</span>
					</template>

					<template v-slot:cell(actions)="row">
						<b-dropdown :text="$t('Actions')" size="sm" right>

							<b-dropdown-item @click="approveKey(row.item)" v-if="!row.item.approved_at" :title="$t('Approve')">
								✅ {{ $t('Approve') }}
							</b-dropdown-item>

							<b-dropdown-item @click="revokeKey(row.item)" :title="$t('Revoke')">
								🚫 {{ $t('Revoke Key') }}
							</b-dropdown-item>

						</b-dropdown>
					</template>

				</b-table>

				<b-alert v-if="loaded && items.length === 0" show variant="info">
					{{ $t('No public keys found. Devices will register their public keys when they connect.') }}
				</b-alert>
			</b-col>
		</b-row>

	</b-container>

</template>

<script>

	import { DeviceService } from '../services/DeviceService';

	export default {

		mounted() {
			this.service = new DeviceService(window.ORGANISATION_ID);
			this.refreshKeys();
		},

		data() {
			return {
				loaded: false,
				items: [],
				fields: [
					{
						key: 'device',
						label: this.$t('Device'),
					},
					{
						key: 'public_key',
						label: this.$t('Public Key'),
					},
					{
						key: 'status',
						label: this.$t('Status'),
					},
					{
						key: 'signed_cards',
						label: this.$t('Signed Cards'),
					},
					{
						key: 'actions',
						label: this.$t('Actions'),
						class: 'text-right'
					}
				]
			}
		},

		methods: {

			async refreshKeys() {
				const result = await this.service.getPublicKeys();
				this.items = result.items;
				this.loaded = true;
			},

			truncateKey(key) {
				if (!key) return '';
				if (key.length > 32) {
					return key.substr(0, 16) + '...' + key.substr(key.length - 16);
				}
				return key;
			},

			async approveKey(item) {
				if (confirm(this.$t('Approve the public key for device "{name}"?', { name: item.name }))) {
					await this.service.approveKey(item.id);
					await this.refreshKeys();
				}
			},

			async revokeKey(item) {
				const cardCount = item.signed_cards_count || 0;
				let warning;
				if (cardCount > 0) {
					warning = this.$t('⚠️ WARNING: This is a destructive action!\n\nRevoking this key will invalidate {count} cards that were last signed by this device.\n\nAre you absolutely sure?', { count: cardCount });
				} else {
					warning = this.$t('Revoke the public key for device "{name}"?', { name: item.name });
				}

				if (confirm(warning)) {
					await this.service.revokeKey(item.id);
					await this.refreshKeys();
				}
			},

			async showSignedCards(item) {
				const cards = await this.service.getSignedCards(item.id);
				alert(this.$t('Cards signed by {name}:', { name: item.name }) + '\n\n' +
					cards.items.map(c => c.uid + ' (balance: ' + c.balance + ')').join('\n'));
			}
		}
	}
</script>
