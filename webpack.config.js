const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'editor/variation': path.resolve( __dirname, 'assets/src/editor/variation.js' ),
		'frontend/lightbox': path.resolve( __dirname, 'assets/src/frontend/lightbox.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
};
