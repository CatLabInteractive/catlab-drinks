/**
* Tests to verify Table Service routes are correctly configured.
*
* - POS: Table service is integrated into Headquarters (not separate routes).
*   POS MUST NOT have standalone waiter/patron/tables routes.
* - Manage app: MUST have tables, waiter, and patron routes for admin access.
* - Client app: MUST NOT reference any table service routes.
*/
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readAppFile(appName) {
	return readFileSync(resolve(__dirname, '..', appName, 'js', 'app.js'), 'utf-8');
}

describe('POS app table service integration', () => {
	const source = readAppFile('pos');

	it('does NOT import standalone Tables component', () => {
		expect(source).not.toContain("import Tables from");
	});

	it('does NOT import standalone WaiterDashboard component', () => {
		expect(source).not.toContain("import WaiterDashboard from");
	});

	it('does NOT import standalone PatronDetail component', () => {
		expect(source).not.toContain("import PatronDetail from");
	});

	it('does NOT have standalone tables route', () => {
		expect(source).not.toContain("name: 'tables'");
	});

	it('does NOT have standalone waiter route', () => {
		expect(source).not.toContain("name: 'waiter'");
	});

	it('does NOT have standalone patron route', () => {
		expect(source).not.toContain("name: 'patron'");
	});

	it('still has headquarters route where table service is integrated', () => {
		expect(source).toContain("name: 'hq'");
	});
});

describe('Manage app table service routing', () => {
	const source = readAppFile('manage');

	it('imports Tables component', () => {
		expect(source).toContain("import Tables from");
	});

	it('imports WaiterDashboard component', () => {
		expect(source).toContain("import WaiterDashboard from");
	});

	it('imports PatronDetail component', () => {
		expect(source).toContain("import PatronDetail from");
	});

	it('has tables route', () => {
		expect(source).toContain("name: 'tables'");
	});

	it('has waiter route', () => {
		expect(source).toContain("name: 'waiter'");
	});

	it('has patron route', () => {
		expect(source).toContain("name: 'patron'");
	});

	it('has tables path', () => {
		expect(source).toContain("/events/:id/tables");
	});

	it('has waiter path', () => {
		expect(source).toContain("/events/:id/waiter");
	});

	it('has patron path', () => {
		expect(source).toContain("/events/:id/patron/:patronId");
	});
});

describe('Client app does not reference table service', () => {
	const source = readAppFile('clients');

	it('does not reference Tables', () => {
		expect(source).not.toContain("import Tables from");
		expect(source).not.toContain("name: 'tables'");
	});

	it('does not reference WaiterDashboard', () => {
		expect(source).not.toContain("WaiterDashboard");
	});

	it('does not reference PatronDetail', () => {
		expect(source).not.toContain("PatronDetail");
	});

	it('does not have waiter route', () => {
		expect(source).not.toContain("name: 'waiter'");
	});
});
