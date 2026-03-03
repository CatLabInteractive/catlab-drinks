/**
 * Unit tests for the SignedCardsModal component.
 *
 * Verifies the component template structure:
 * - Has a signed cards list modal with a table
 * - Has a card details modal for clicking individual cards
 * - Card UIDs are rendered as clickable links
 * - Balance is formatted using VisibleAmount
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const componentSource = readFileSync(
	resolve(__dirname, '..', 'shared', 'js', 'components', 'SignedCardsModal.vue'),
	'utf-8'
);

function extractTemplate(source) {
	const startIdx = source.indexOf('<template>');
	const endIdx = source.lastIndexOf('</template>');
	return (startIdx >= 0 && endIdx > startIdx) ? source.substring(startIdx, endIdx + '</template>'.length) : '';
}

function extractScript(source) {
	const startIdx = source.indexOf('<script>');
	const endIdx = source.lastIndexOf('</script>');
	return (startIdx >= 0 && endIdx > startIdx) ? source.substring(startIdx, endIdx + '</script>'.length) : '';
}

const template = extractTemplate(componentSource);
const script = extractScript(componentSource);

describe('SignedCardsModal component template', () => {

	it('has a signed cards modal with ref "signedCardsModal"', () => {
		expect(template).toContain('ref="signedCardsModal"');
	});

	it('has a card details modal with ref "cardModal"', () => {
		expect(template).toContain('ref="cardModal"');
	});

	it('uses b-modal for the signed cards list', () => {
		expect(template).toContain('<b-modal');
	});

	it('uses b-table to display cards', () => {
		expect(template).toContain('<b-table');
	});

	it('renders card UIDs as clickable links', () => {
		expect(template).toContain('showCardDetails(row.item)');
	});

	it('formats balance using formatBalance', () => {
		expect(template).toContain('formatBalance(row.item.balance)');
	});

	it('shows a spinner while loading', () => {
		expect(template).toContain('<b-spinner');
	});

	it('shows empty state when no cards found', () => {
		expect(template).toContain('No signed cards found.');
	});

	it('includes card-details component for card detail view', () => {
		expect(template).toContain('card-details');
		expect(template).toContain('cardDetails.uid');
	});
});

describe('SignedCardsModal component script', () => {

	it('imports CardDetails component', () => {
		expect(script).toContain('import CardDetails from');
	});

	it('imports VisibleAmount for balance formatting', () => {
		expect(script).toContain('import {VisibleAmount}');
	});

	it('has a show method that accepts service and item', () => {
		expect(script).toContain('async show(service, item)');
	});

	it('has a showCardDetails method', () => {
		expect(script).toContain('showCardDetails(card)');
	});

	it('has a formatBalance method using VisibleAmount', () => {
		expect(script).toContain('formatBalance(balance)');
		expect(script).toContain('VisibleAmount.toVisible(balance)');
	});

	it('calls service.getSignedCards to fetch cards', () => {
		expect(script).toContain('service.getSignedCards(item.id)');
	});
});
