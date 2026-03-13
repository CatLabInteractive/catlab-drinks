<template>
	<b-container fluid>
		<h1>
			{{ $t('Tables') }}
		</h1>

		<div class="text-center" v-if="!loaded">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<b-row v-if="loaded">
			<b-col>
				<b-card class="mb-3">
					<b-form inline @submit.prevent="generateTables">
						<label class="mr-2">{{ $t('Generate tables') }}:</label>
						<b-form-input
							type="number"
							v-model="generateCount"
							min="1"
							max="100"
							size="sm"
							class="mr-2"
							style="width: 80px"
						></b-form-input>
						<b-button size="sm" variant="success" @click="generateTables" :disabled="generating">
							<b-spinner small v-if="generating"></b-spinner>
							{{ $t('Generate') }}
						</b-button>
					</b-form>
				</b-card>

				<b-table striped hover :items="items" :fields="fields">
					<template v-slot:cell(name)="row">
						<span v-if="editingId !== row.item.id">{{ row.item.name }}</span>
						<b-form-input
							v-else
							v-model="editModel.name"
							size="sm"
							@keyup.enter="saveEdit(row.item)"
						></b-form-input>
					</template>

					<template v-slot:cell(table_number)="row">
						{{ row.item.table_number }}
					</template>

					<template v-slot:cell(actions)="row">
						<template v-if="editingId !== row.item.id">
							<b-button size="sm" variant="outline-primary" @click="startEdit(row.item)" class="mr-1">
								✏️
							</b-button>
							<b-button size="sm" variant="outline-danger" @click="remove(row.item)">
								🗑️
							</b-button>
						</template>
						<template v-else>
							<b-button size="sm" variant="success" @click="saveEdit(row.item)" class="mr-1">
								✓
							</b-button>
							<b-button size="sm" variant="outline-secondary" @click="cancelEdit()">
								✕
							</b-button>
						</template>
					</template>
				</b-table>

				<b-alert v-if="items.length === 0" variant="info" show>
					{{ $t('No tables configured for this event. Use the generate button above to create tables.') }}
				</b-alert>
			</b-col>
		</b-row>
	</b-container>
</template>

<script>
import {TableService} from "../../../shared/js/services/TableService";

export default {
	mounted() {
		this.eventId = this.$route.params.id;
		this.service = new TableService(this.eventId);
		this.refreshTables();
	},

	data() {
		return {
			loaded: false,
			generating: false,
			generateCount: 10,
			editingId: null,
			editModel: {},
			items: [],
			fields: [
				{
					key: 'table_number',
					label: this.$t('#'),
					class: 'text-center',
					sortable: true
				},
				{
					key: 'name',
					label: this.$t('Name'),
					sortable: true
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
		async refreshTables() {
			this.items = (await this.service.index({ sort: 'table_number' })).items;
			this.loaded = true;
		},

		async generateTables() {
			this.generating = true;
			try {
				await this.service.bulkGenerate(this.generateCount);
				await this.refreshTables();
			} finally {
				this.generating = false;
			}
		},

		startEdit(item) {
			this.editingId = item.id;
			this.editModel = { name: item.name };
		},

		cancelEdit() {
			this.editingId = null;
			this.editModel = {};
		},

		async saveEdit(item) {
			await this.service.update(item.id, this.editModel);
			this.editingId = null;
			this.editModel = {};
			await this.refreshTables();
		},

		async remove(item) {
			if (confirm(this.$t('Are you sure you want to remove this table?'))) {
				await this.service.delete(item.id);
				await this.refreshTables();
			}
		}
	}
}
</script>
