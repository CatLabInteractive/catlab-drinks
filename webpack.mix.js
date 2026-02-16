const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

 // Tell Mix to use Vue 3 and the appropriate compat options when compiling .vue files
mix.vue({
	version: 3,
	options: {
	  compilerOptions: {
		compatConfig: {
		  MODE: 3,
		  WATCH_ARRAY: false,
		},
	  },
	},
  })

// Alias Vue and (optionally) the composition API
mix.webpackConfig(() => {
return {
	resolve: {
	alias: {
		vue: "@vue/compat",
		"@vue/composition-api": "@vue/compat",
	},
	fallback: {
		"stream": require.resolve("stream-browserify")
	}
	},
}
})

mix.setPublicPath('public');

mix.sass('resources/sass/app.scss', 'css');

mix.js('resources/swagger/swagger-ui.js', 'swaggerui')
	.sass('resources/swagger/swagger.scss', 'swaggerui');

// Manage
mix.ts('resources/manage/js/app.js', 'res/manage.js')
	.sass('resources/manage/sass/app.scss', 'res/manage.css');


// POS (warning: these - and only these - will be used by the android app)
mix.ts('resources/pos/js/app.js', 'res/pos.js')
	.sass('resources/pos/sass/app.scss', 'res/pos.css');

// Qr
mix.ts('resources/manage/js/qrGenerator.js', 'res/sales/js');

// Order
mix.ts('resources/clients/js/app.js', 'res/clients/js')
	.sass('resources/clients/sass/app.scss', 'res/clients/css');
