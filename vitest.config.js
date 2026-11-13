import { defineConfig } from 'vitest/config';
import { parse } from '@vue/compiler-sfc';

/**
 * Lets a test import a .vue file as a module: the module is the content of
 * its <script> block (the component options, without template). Tests use it
 * to run the data, computed values and methods of a component.
 */
const vueScriptOnly = {
	name: 'vue-script-only',
	transform(code, id) {
		if (!id.endsWith('.vue')) {
			return null;
		}
		const { descriptor, errors } = parse(code, { filename: id });
		if (errors.length > 0) {
			throw errors[0];
		}
		return { code: descriptor.script ? descriptor.script.content : 'export default {};', map: null };
	},
};

export default defineConfig({
	plugins: [vueScriptOnly],
	test: {
		environment: 'jsdom',
		include: ['resources/**/*.test.js'],
	},
	resolve: {
		alias: {
			vue: '@vue/compat',
		},
	},
});
