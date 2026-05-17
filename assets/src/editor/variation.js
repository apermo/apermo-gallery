import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockVariation( 'core/gallery', {
	name: 'apermo-gallery',
	title: __( 'Photo Gallery', 'apermo-gallery' ),
	description: __(
		'A Flickr-style gallery with EXIF data and a filmstrip in the lightbox.',
		'apermo-gallery'
	),
	isDefault: false,
	attributes: {
		className: 'is-apermo-gallery',
	},
	scope: [ 'inserter' ],
} );
