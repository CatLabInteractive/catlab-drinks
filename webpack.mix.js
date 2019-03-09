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

mix.sass('resources/sass/app.scss', 'public/css');

mix.js('resources/swagger/swagger-ui.js', 'public/swaggerui')
    .sass('resources/swagger/swagger.scss', 'public/swaggerui');

// Client
mix.js('resources/sales/js/app.js', 'public/res/sales/js')
    .sass('resources/sales/sass/app.scss', 'public/res/sales/css');

// Order
mix.js('resources/clients/js/app.js', 'public/res/clients/js')
    .sass('resources/clients/sass/app.scss', 'public/res/clients/css');
