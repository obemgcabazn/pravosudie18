;'use strict';

const { watch } = require( 'gulp' );
const livereload = require( 'gulp-livereload' );

const themeDir = "public/wp-content/themes/kanon/";

const startWatch = () => {
  livereload.listen();
  watch( themeDir + '**/*.php' ).on( 'change', livereload.changed );
  watch( themeDir + 'css/*.css' ).on( 'change', livereload.changed );
  watch( themeDir + 'js/**/*.js' ).on( 'change', livereload.changed );
}
exports.default = startWatch;