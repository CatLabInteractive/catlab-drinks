/**
 * Integration tests to verify that PublicKeys and Devices views
 * use the SignedCardsModal component instead of alert() for signed cards.
 *
 * - Both views MUST import and register SignedCardsModal
 * - Both views MUST have a <signed-cards-modal> ref in the template
 * - Both views MUST NOT use alert() in showSignedCards
 * - Both views MUST delegate to the modal's show() method
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function readManageView(viewName) {
	return readFileSync(
		resolve(__dirname, '..', 'manage', 'js', 'views', viewName + '.vue'),
		'utf-8'
	);
}

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

describe('PublicKeys.vue signed cards modal integration', () => {
	const source = readManageView('PublicKeys');
	const template = extractTemplate(source);
	const script = extractScript(source);

	it('imports SignedCardsModal component', () => {
		expect(script).toContain("import SignedCardsModal from");
	});

	it('registers signed-cards-modal component', () => {
		expect(script).toContain("'signed-cards-modal'");
	});

	it('has signed-cards-modal ref in template', () => {
		expect(template).toContain('ref="signedCardsModal"');
	});

	it('does NOT use alert() in showSignedCards', () => {
		const methodStart = script.indexOf('showSignedCards');
		// Extract until the next method or end of methods block
		const methodEnd = script.indexOf('\n\t\t\t},', methodStart);
		const methodBlock = script.substring(methodStart, methodEnd > methodStart ? methodEnd : methodStart + 300);
		expect(methodBlock).not.toContain('alert(');
	});

	it('delegates to signedCardsModal.show()', () => {
		expect(script).toContain('this.$refs.signedCardsModal.show(');
	});
});

describe('Devices.vue signed cards modal integration', () => {
	const source = readManageView('Devices');
	const template = extractTemplate(source);
	const script = extractScript(source);

	it('imports SignedCardsModal component', () => {
		expect(script).toContain("import SignedCardsModal from");
	});

	it('registers signed-cards-modal component', () => {
		expect(script).toContain("'signed-cards-modal'");
	});

	it('has signed-cards-modal ref in template', () => {
		expect(template).toContain('ref="signedCardsModal"');
	});

	it('does NOT use alert() in showSignedCards', () => {
		const methodStart = script.indexOf('showSignedCards');
		// Extract until the next method or end of methods block
		const methodEnd = script.indexOf('\n\t\t\t},', methodStart);
		const methodBlock = script.substring(methodStart, methodEnd > methodStart ? methodEnd : methodStart + 300);
		expect(methodBlock).not.toContain('alert(');
	});

	it('delegates to signedCardsModal.show()', () => {
		expect(script).toContain('this.$refs.signedCardsModal.show(');
	});
});
