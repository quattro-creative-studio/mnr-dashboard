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
   .sass('resources/sass/app.scss', 'public/css')
    .version();

/*
 |--------------------------------------------------------------------------
 | TinyMCE, copied rather than bundled
 |--------------------------------------------------------------------------
 |
 | Copied verbatim so it stays a separate, unmodified file rather than being
 | concatenated into app.js. Only the parts the two admin views actually use
 | are copied: the full package is 10 MB, almost all of it plugins and skins
 | this application never loads.
 |
 | TinyMCE resolves its own theme, model, plugin and skin relative to the URL
 | of tinymce.min.js, so the directory layout below must be preserved as-is.
 | license.md travels with it: distributing the library means distributing its
 | licence.
 |
 */

const tinymce = 'node_modules/tinymce';
const vendor = 'public/vendor/tinymce';

mix.copy(`${tinymce}/tinymce.min.js`, `${vendor}/tinymce.min.js`)
   .copy(`${tinymce}/license.md`, `${vendor}/license.md`)
   .copy(`${tinymce}/themes/silver/theme.min.js`, `${vendor}/themes/silver/theme.min.js`)
   .copy(`${tinymce}/models/dom/model.min.js`, `${vendor}/models/dom/model.min.js`)
   .copy(`${tinymce}/icons/default/icons.min.js`, `${vendor}/icons/default/icons.min.js`)
   .copy(`${tinymce}/plugins/link/plugin.min.js`, `${vendor}/plugins/link/plugin.min.js`)
   .copyDirectory(`${tinymce}/skins/ui/oxide`, `${vendor}/skins/ui/oxide`)
   .copyDirectory(`${tinymce}/skins/content/default`, `${vendor}/skins/content/default`);
