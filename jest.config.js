module.exports = {
	preset: 'ts-jest',
	testEnvironment: 'node',
	roots: ['<rootDir>/tests/js'],
	// The .test.js files in tests/js are Vitest tests.
	testMatch: ['**/*.test.ts'],
	transform: {
		'^.+\\.tsx?$': ['ts-jest', {
			tsconfig: {
				target: 'es2015',
				module: 'commonjs',
				moduleResolution: 'node',
				strict: true,
				esModuleInterop: true,
				lib: ['dom', 'es2015'],
				skipLibCheck: true,
				// TypeScript 6 no longer loads every @types package by itself
				types: ['jest', 'node']
			}
		}]
	},
	moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json'],
};
