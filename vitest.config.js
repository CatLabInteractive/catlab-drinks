import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Note: unlike the webpack build, tests deliberately do NOT alias
// vue -> @vue/compat. The compat runtime is only needed for bootstrap-vue
// (which component tests stub out), and mixing the compat runtime with
// @vue/test-utils' own vue import creates two Vue instances that cannot
// resolve each other's components. Templates are compiled in MODE 3
// (standard Vue 3 semantics) in both builds, so behaviour matches.
export default defineConfig({
	plugins: [vue()],
	test: {
		environment: 'jsdom',
		include: ['resources/**/*.test.js'],
	},
});
