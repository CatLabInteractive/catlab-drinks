/**
 * Source-level tests for the Manage EventForm component (the event wizard).
 * Vitest does not compile SFCs here, so these assert on the template and
 * script text, like the other *-views tests.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const source = readFileSync(resolve(__dirname, '..', 'manage', 'js', 'components', 'EventForm.vue'), 'utf-8');

describe('EventForm.vue', () => {
	it('takes event, mode and saving props', () => {
		expect(source).toMatch(/props:\s*{[\s\S]*event:[\s\S]*mode:[\s\S]*saving:/);
	});
	it('uses the mapping module', () => {
		expect(source).toContain("from '../services/eventSettings'");
		expect(source).toContain('answersFromEvent(');
		expect(source).toContain('eventFromAnswers(');
		expect(source).toContain('validateAnswers(');
	});
	it('asks the three questions with literal translation keys', () => {
		expect(source).toContain("$t('What is the event called?')");
		expect(source).toContain("$t('How do your guests pay?')");
		expect(source).toContain("$t('Can guests order from their own phone?')");
	});
	it('offers the three payment tiles and the three online ordering tiles', () => {
		for (const key of ['NFC topup cards', 'Cash', 'Vouchers', 'No, only at the bar',
			'Yes, and we handle payment ourselves', 'Yes, through an integration that passes the card']) {
			expect(source).toContain(`$t('${key}')`);
		}
	});
	it('keeps the advanced settings reachable', () => {
		expect(source).toContain("$t('Advanced settings')");
		expect(source).toContain('answers.split_orders_by_categories');
		expect(source).toContain('answers.checkin_url');
	});
	it('routes Enter through onSubmit instead of submitting directly', () => {
		expect(source).toContain('@submit.prevent="onSubmit"');
	});
	it('re-derives answers when the event prop changes', () => {
		expect(source).toMatch(/watch:\s*{[\s\S]*event\s*[:(]/);
	});
	it('drops a chosen integration when cards are unticked', () => {
		expect(source).toMatch(/['"]answers\.payment_cards['"]\s*[:(]/);
	});
});

describe('EventForm.vue review fixes', () => {
	it('takes the voucher value as decimal text, never as a number input', () => {
		// Chrome strips a typed decimal comma from <input type="number">, turning "2,50" into 250.
		expect(source).not.toContain('type="number"');
		expect(source).toMatch(/inputmode="decimal"/);
	});
	it('collapses the compact payment tiles to one column at phone width', () => {
		expect(source).toMatch(/@media\s*\(max-width:\s*575\.98px\)[\s\S]*\.event-form__tiles--compact\s*{\s*grid-template-columns:\s*1fr/);
	});
	it('marks tiles with a checkbox or radio control', () => {
		expect(source).toContain('event-tile__control--check');
		expect(source).toContain('event-tile__control--radio');
	});
});
