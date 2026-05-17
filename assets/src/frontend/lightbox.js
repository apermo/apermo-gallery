import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import './gallery.css';

const escapeHtml = ( value ) =>
	value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );

const captionFor = ( linkElement ) => {
	const figcaption = linkElement.closest( 'figure' )?.querySelector( 'figcaption' );
	const fromFigcaption = figcaption?.textContent?.trim() ?? '';

	if ( fromFigcaption !== '' ) {
		return fromFigcaption;
	}

	return ( linkElement.querySelector( 'img' )?.alt ?? '' ).trim();
};

const initGallery = ( container ) => {
	const lightbox = new PhotoSwipeLightbox( {
		gallery: container,
		children: 'a[data-apermo-exif]',
		pswpModule: () => import( 'photoswipe' ),
	} );

	lightbox.on( 'uiRegister', () => {
		lightbox.pswp.ui.registerElement( {
			name: 'apermo-caption',
			order: 9,
			isButton: false,
			appendTo: 'root',
			html: '',
			onInit: ( el, pswp ) => {
				pswp.on( 'change', () => {
					const link = pswp.currSlide?.data?.element;

					if ( ! link ) {
						el.innerHTML = '';
						return;
					}

					const exif = link.dataset.apermoExif || '';
					const caption = captionFor( link );

					let html = '';
					if ( caption !== '' ) {
						html += `<div class="apermo-gallery-caption">${ escapeHtml( caption ) }</div>`;
					}
					if ( exif !== '' ) {
						html += `<div class="apermo-gallery-exif">${ escapeHtml( exif ) }</div>`;
					}

					el.innerHTML = html;
				} );
			},
		} );
	} );

	lightbox.init();
};

const bootstrap = () => {
	document.querySelectorAll( '.apermo-gallery' ).forEach( initGallery );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
