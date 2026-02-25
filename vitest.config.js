import { defineConfig } from 'vitest/config';

export default defineConfig({
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
