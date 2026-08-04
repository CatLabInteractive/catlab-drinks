/**
 * Lightweight bootstrap-vue replacements for component tests.
 *
 * The real bootstrap-vue is not mountable under the test setup (and would
 * make tests slow and brittle); these stubs keep the DOM semantics the
 * components rely on: slots render, click listeners fall through,
 * checkboxes drive v-model, modals expose show()/hide() and emit
 * `hidden`, and tables render their scoped cell slots per item.
 */

const slotDiv = { template: '<div><slot /></div>' };

export const BModalStub = {
	props: ['title'],
	emits: ['hide', 'hidden', 'ok'],
	data() {
		return { visible: false };
	},
	methods: {
		show() {
			this.visible = true;
		},
		hide() {
			this.visible = false;
			this.$emit('hide');
			this.$emit('hidden');
		},
	},
	template: '<div v-if="visible" class="modal-stub" :data-title="title"><slot /></div>',
};

export const BFormCheckboxStub = {
	props: ['modelValue'],
	emits: ['update:modelValue'],
	template:
		'<label><input type="checkbox" :checked="modelValue" ' +
		'@change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
};

export const BTableStub = {
	props: ['items', 'fields'],
	template:
		'<div class="table-stub">' +
		'<div v-for="(item, index) in items" :key="index" class="table-row-stub">' +
		'<slot name="cell(items)" :item="item" />' +
		'<slot name="cell(status)" :item="item" />' +
		'<slot name="cell(payment_status)" :item="item" />' +
		'<slot name="cell(price)" :item="item" />' +
		'<slot name="cell(actions)" :item="item" />' +
		'</div></div>',
};

export const BTabsStub = {
	props: ['modelValue'],
	emits: ['update:modelValue'],
	template: '<div><slot /></div>',
};

export const bootstrapVueComponents = {
	'b-modal': BModalStub,
	'b-form-checkbox': BFormCheckboxStub,
	'b-table': BTableStub,
	'b-tabs': BTabsStub,
	'b-tab': slotDiv,
	'b-row': slotDiv,
	'b-col': slotDiv,
	'b-card': slotDiv,
	'b-form-group': slotDiv,
	'b-list-group': slotDiv,
	'b-list-group-item': slotDiv,
	'b-alert': slotDiv,
	'b-button': { template: '<button type="button"><slot /></button>' },
	'b-badge': { template: '<span><slot /></span>' },
	'b-spinner': { template: '<span class="spinner-stub"></span>' },
};

/**
 * Common mount options: bootstrap-vue stubs plus a $t that returns the key
 * (with simple {placeholder} interpolation, mirroring vue-i18n fallback).
 */
export function globalMountOptions(extra = {}) {
	return {
		components: { ...bootstrapVueComponents, ...(extra.components || {}) },
		mocks: {
			$t: (key, values) =>
				values
					? key.replace(/\{(\w+)\}/g, (m, name) => (name in values ? values[name] : m))
					: key,
			...(extra.mocks || {}),
		},
		stubs: extra.stubs || {},
	};
}
