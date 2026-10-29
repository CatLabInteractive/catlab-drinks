/**
 * Tests to verify CheckIn links are correctly placed in Events views.
 *
 * - POS Events: MUST have "Check-In attendees" action link
 * - Manage Events: MUST NOT have "Check-In attendees" link, MUST have "Register attendees"
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readVueTemplate(appName) {
	const content = readFileSync(
		resolve(__dirname, '..', appName, 'js', 'views', 'Events.vue'),
		'utf-8'
	);
	// Extract entire file content between the first <template> and last </template>
	const startIdx = content.indexOf('<template>');
	const endIdx = content.lastIndexOf('</template>');
	return (startIdx >= 0 && endIdx > startIdx) ? content.substring(startIdx, endIdx + '</template>'.length) : '';
}

describe('POS Events.vue template', () => {
	const template = readVueTemplate('pos');

	it('has Check-In attendees action link', () => {
		expect(template).toContain('Check-In attendees');
	});

	it('links to checkIn route', () => {
		expect(template).toContain("name: 'checkIn'");
	});

	it('has Sales overview link', () => {
		expect(template).toContain('Sales overview');
	});

	it('has Order history link', () => {
		expect(template).toContain('Order history');
	});
});

describe('Manage Events.vue template', () => {
	const template = readVueTemplate('manage');

	it('does NOT have Check-In attendees link', () => {
		expect(template).not.toContain('Check-In attendees');
	});

	it('does NOT link to checkIn route', () => {
		expect(template).not.toContain("name: 'checkIn'");
	});

	it('has Register attendees link', () => {
		expect(template).toContain('Register attendees');
	});

	it('has attendees route link', () => {
		expect(template).toContain("name: 'attendees'");
	});
});

function readVueFile(appName) {
	return readFileSync(
		resolve(__dirname, '..', appName, 'js', 'views', 'Events.vue'),
		'utf-8'
	);
}

describe('Manage Events.vue order token modal', () => {
	const template = readVueTemplate('manage');

	it('does NOT have an order_token table column', () => {
		expect(readVueFile('manage')).not.toContain("key: 'order_token'");
	});

	it('does NOT have an order_token cell slot', () => {
		expect(template).not.toContain('cell(order_token)');
	});

	it('has a Remote order token action item', () => {
		expect(template).toContain('Remote order token');
	});

	it('has an order token modal', () => {
		expect(template).toContain('ref="orderTokenModal"');
	});

	it('shows the full order token in the modal', () => {
		expect(template).toContain('full_order_token');
	});

	it('shows the order page URL in the modal', () => {
		expect(template).toContain('order_url');
	});

	it('mentions QuizWitz as an integrating client', () => {
		expect(template).toContain('QuizWitz');
	});
});

describe('POS Events.vue order token cleanup', () => {
	it('has no order_token remnants', () => {
		expect(readVueFile('pos')).not.toContain('order_token');
	});
});

describe('Manage Events.vue event form', () => {
	const file = readVueFile('manage');
	const template = readVueTemplate('manage');

	it('renders the EventForm component inside the modal', () => {
		expect(file).toContain("import EventForm from '../components/EventForm.vue'");
		expect(template).toContain('<event-form');
		expect(template).toContain('@submit="save"');
	});
	it('switches between wizard and single mode on the event id', () => {
		expect(template).toMatch(/:mode="model\.id \? 'single' : 'wizard'"/);
	});
	it('no longer renders the raw flag checkboxes', () => {
		expect(template).not.toContain('model.allow_unpaid_online_orders');
		expect(template).not.toContain('model.payment_cards');
		expect(template).not.toContain('Allow unpaid online orders');
	});
	it('titles the wizard with the step number', () => {
		expect(file).toContain("$t('New event: step {step} of 3'");
	});
	it('resets to an empty model so the form falls back to create defaults', () => {
		expect(file).toMatch(/resetForm\(\)\s*{\s*this\.model = {};/);
	});
});

describe('Manage Events.vue modal reset timing', () => {
	it('resets the form after the modal has finished hiding, not while it fades out', () => {
		const template = readVueTemplate('manage');
		expect(template).toContain('@hidden="resetForm"');
		expect(template).not.toContain('@hide="resetForm"');
	});
});
