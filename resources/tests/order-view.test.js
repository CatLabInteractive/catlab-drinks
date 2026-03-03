/**
 * Tests for Client Order.vue bugs:
 *
 * 1. The 'Your table number: {tableNumber}' translation key must exist in all i18n files
 *    so that vue-i18n can interpolate the variable correctly.
 * 2. The confirmModal must be explicitly hidden after a successful order submission
 *    to prevent users from accidentally resubmitting the same order.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const orderVueSource = readFileSync(
	resolve(__dirname, '..', 'clients', 'js', 'views', 'Order.vue'),
	'utf-8'
);

function readI18nFile(lang) {
	return readFileSync(
		resolve(__dirname, '..', 'shared', 'js', 'i18n', `${lang}.js`),
		'utf-8'
	);
}

describe('Order.vue - table number translation', () => {
	const translationKey = 'Your table number: {tableNumber}';

	it('uses the translation key in the template', () => {
		expect(orderVueSource).toContain(translationKey);
	});

	it.each(['en', 'nl', 'fr', 'de', 'es'])('has the translation key in %s.js', (lang) => {
		const i18nSource = readI18nFile(lang);
		expect(i18nSource).toContain(`'${translationKey}'`);
	});
});

describe('Order.vue - confirmModal closes after successful order', () => {
	it('hides confirmModal before showing successModal in confirmOrder', () => {
		// Extract the script section
		const scriptStart = orderVueSource.indexOf('<script>');
		const scriptEnd = orderVueSource.indexOf('</script>');
		const scriptSection = orderVueSource.substring(scriptStart, scriptEnd);

		// Verify this.$refs.confirmModal.hide() appears before this.$refs.successModal.show()
		const confirmHideIndex = scriptSection.indexOf('this.$refs.confirmModal.hide()');
		const successShowIndex = scriptSection.indexOf('this.$refs.successModal.show()');

		expect(confirmHideIndex).toBeGreaterThan(-1);
		expect(successShowIndex).toBeGreaterThan(-1);
		expect(confirmHideIndex).toBeLessThan(successShowIndex);
	});
});
