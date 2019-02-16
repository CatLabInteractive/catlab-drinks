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

mix.js('resources/js/app.js', 'public/js')
   .sass('resources/sass/app.scss', 'public/css');

mix.js('resources/js/swagger-ui.js', 'public/swaggerui')
    .sass('resources/sass/swagger.scss', 'public/swaggerui');

// Client
mix.js('resources/client/js/app.js', 'public/client-res/js')
    .sass('resources/client/sass/app.scss', 'public/client-res/css');
