/**
 * Tests to verify CheckIn routing is correctly configured in each app.
 *
 * - POS app: MUST have the checkIn route and import CheckIn
 * - Manage app: MUST NOT have the checkIn route or import CheckIn
 * - Client app: MUST NOT reference checkIn at all (unaffected)
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readAppFile(appName) {
	return readFileSync(resolve(__dirname, '..', appName, 'js', 'app.js'), 'utf-8');
}

describe('POS app routing', () => {
	const source = readAppFile('pos');

	it('imports CheckIn component', () => {
		expect(source).toContain("import CheckIn from");
	});

	it('has checkIn route', () => {
		expect(source).toContain("name: 'checkIn'");
	});

	it('has check-in path', () => {
		expect(source).toContain("/events/:id/check-in");
	});
});

describe('Manage app routing', () => {
	const source = readAppFile('manage');

	it('does not import CheckIn component', () => {
		expect(source).not.toContain("import CheckIn from");
	});

	it('does not have checkIn route', () => {
		expect(source).not.toContain("name: 'checkIn'");
	});

	it('does not have check-in path', () => {
		expect(source).not.toContain("/events/:id/check-in");
	});

	it('still has attendees route', () => {
		expect(source).toContain("name: 'attendees'");
	});
});

describe('Client app routing', () => {
	const source = readAppFile('clients');

	it('does not reference checkIn', () => {
		expect(source).not.toContain('checkIn');
		expect(source).not.toContain('check-in');
		expect(source).not.toContain('CheckIn');
	});
});
