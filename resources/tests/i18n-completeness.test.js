/**
 * i18n completeness test.
 *
 * Extracts every string-literal $t('...') key used by the frontend apps
 * and asserts that every supported locale defines a translation for it.
 * Locales fall back to English (the key itself), so a missing entry only
 * shows up for non-English users — this test catches it at build time.
 */
import { describe, it, expect } from 'vitest';
import { readdirSync, readFileSync } from 'fs';
import { resolve, join } from 'path';

import en from '../shared/js/i18n/en';
import nl from '../shared/js/i18n/nl';
import fr from '../shared/js/i18n/fr';
import de from '../shared/js/i18n/de';
import es from '../shared/js/i18n/es';

const APPS = ['manage', 'pos', 'clients', 'shared'];
const LOCALES = { en, nl, fr, de, es };

function sourceFiles() {
	const files = [];
	const walk = (dir) => {
		for (const entry of readdirSync(dir, { withFileTypes: true })) {
			const path = join(dir, entry.name);
			if (entry.isDirectory()) {
				walk(path);
			} else if (/\.(vue|js|ts)$/.test(entry.name) && !/\.test\./.test(entry.name)) {
				files.push(path);
			}
		}
	};
	for (const app of APPS) {
		walk(resolve(__dirname, '..', app, 'js'));
	}
	return files;
}

function usedTranslationKeys() {
	const keys = new Set();
	const re = /\$t\(\s*('(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")/g;
	for (const file of sourceFiles()) {
		const source = readFileSync(file, 'utf-8');
		let match;
		while ((match = re.exec(source)) !== null) {
			// Evaluate the literal so escapes (\n, \') resolve exactly
			// like they do at runtime.
			keys.add(new Function('return ' + match[1])());
		}
	}
	return [...keys];
}

const keys = usedTranslationKeys();

describe('i18n completeness', () => {
	it('extracts a plausible number of translation keys', () => {
		expect(keys.length).toBeGreaterThan(300);
	});

	for (const [locale, messages] of Object.entries(LOCALES)) {
		it(`locale '${locale}' defines every used translation key`, () => {
			const missing = keys.filter((key) => !(key in messages));
			expect(missing).toEqual([]);
		});
	}
});
