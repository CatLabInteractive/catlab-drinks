/**
 * Checks that the Vue 2 patterns removed in pull request 1 of the interface
 * migration do not come back.
 */
import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

/** All .vue and .js files below resources/, without test files. */
function sourceFiles() {
	const result = [];
	const walk = (dir) => {
		for (const entry of fs.readdirSync(path.join(root, dir), { withFileTypes: true })) {
			const relative = dir ? path.join(dir, entry.name) : entry.name;
			if (entry.isDirectory()) {
				if (entry.name === 'node_modules' || entry.name === 'tests') {
					continue;
				}
				walk(relative);
			} else if (/\.(vue|js)$/.test(entry.name) && !/\.test\.js$/.test(entry.name)) {
				result.push(relative);
			}
		}
	};
	walk('');
	return result.sort();
}

describe('Vue 2 patterns that pull request 1 removed', () => {
	it('no source file uses Vue.prototype', () => {
		const hits = sourceFiles().filter((f) => /Vue\.prototype\.\$/.test(read(f)));
		expect(hits).toEqual([]);
	});

	it('manage and pos attach the services to the app', () => {
		for (const file of ['manage/js/app.js', 'pos/js/app.js']) {
			expect(read(file)).toContain('Object.assign(app.config.globalProperties, services)');
		}
	});

	it('no component uses the beforeDestroy or destroyed hook', () => {
		const hits = sourceFiles().filter((f) => /\b(beforeDestroy|destroyed)\s*\(/.test(read(f)));
		expect(hits).toEqual([]);
	});

	it('no template uses a BootstrapVue alias tag', () => {
		const hits = sourceFiles().filter((f) => /<\/?b-(btn|form-fieldset)\b/.test(read(f)));
		expect(hits).toEqual([]);
	});

	it('no bootstrap.js loads Popper', () => {
		for (const app of ['manage', 'pos', 'clients']) {
			expect(read(app + '/js/bootstrap.js')).not.toContain('popper');
		}
	});
});
