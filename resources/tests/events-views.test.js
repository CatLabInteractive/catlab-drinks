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
