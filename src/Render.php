<?php

declare(strict_types=1);

namespace Apermo\Gallery;

use WP_HTML_Tag_Processor;

/**
 * Rewrites core/gallery output when the wrapper carries the `is-apermo-gallery` class.
 *
 * For every inner image:
 *   - swap `src`/`srcset` over to the three Apermo Gallery sizes,
 *   - wrap the `<img>` in an anchor pointing at the large size with a
 *     formatted EXIF caption on `data-apermo-exif`,
 *   - flag the attachment so derivative cleanup runs on first publish.
 */
class Render {

	private const SRCSET_WIDTHS = [
		ImageSizes::THUMB  => 400,
		ImageSizes::MEDIUM => 1000,
		ImageSizes::LARGE  => 1600,
	];

	/**
	 * Holds the per-attachment data captured during attribute rewriting and
	 * consumed by the anchor-wrapping pass.
	 *
	 * @var array<int, array{href: string, width: int, height: int, exif: string}>
	 */
	private static array $anchor_data = [];

	/**
	 * Registers the WordPress hooks owned by this component.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block', [ self::class, 'filter' ], 10, 2 );
	}

	/**
	 * Filters the rendered gallery markup, returning it unchanged when the gallery is not opted in.
	 *
	 * @param string               $block_content The block's rendered HTML.
	 * @param array<string, mixed> $block         The parsed block.
	 *
	 * @return string
	 */
	public static function filter( string $block_content, array $block ): string {
		if ( ( $block['blockName'] ?? '' ) !== 'core/gallery' ) {
			return $block_content;
		}

		$class_name = (string) ( $block['attrs']['className'] ?? '' );

		if ( ! \str_contains( $class_name, 'is-apermo-gallery' ) ) {
			return $block_content;
		}

		$rewritten = self::rewrite_image_attributes( $block_content );
		$wrapped   = self::wrap_images_with_anchors( $rewritten );

		return self::stamp_container_class( $wrapped );
	}

	/**
	 * Rewrites every `<img>`'s src and srcset to the Apermo Gallery sizes, populating
	 * `$anchor_data` with the per-attachment payload used in the wrapping pass.
	 *
	 * @param string $html The original block HTML.
	 *
	 * @return string
	 */
	private static function rewrite_image_attributes( string $html ): string {
		self::$anchor_data = [];

		$processor = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$attachment_id = self::extract_attachment_id( (string) $processor->get_attribute( 'class' ) );

			if ( $attachment_id === 0 ) {
				continue;
			}

			self::apply_apermo_sizes( $processor, $attachment_id );
			self::record_anchor_data( $attachment_id );

			AttachmentFlag::flag( $attachment_id );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Extracts the attachment ID from a `wp-image-NNN` class string, or returns 0.
	 *
	 * @param string $class_attribute The raw class attribute value.
	 *
	 * @return int
	 */
	private static function extract_attachment_id( string $class_attribute ): int {
		if ( \preg_match( '/\bwp-image-(\d+)\b/', $class_attribute, $matches ) !== 1 ) {
			return 0;
		}

		return (int) $matches[1];
	}

	/**
	 * Sets the medium URL on `src` and a thumb/medium/large srcset on the current `<img>` tag.
	 *
	 * @param WP_HTML_Tag_Processor $processor     The processor positioned at an `<img>`.
	 * @param int                   $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private static function apply_apermo_sizes( WP_HTML_Tag_Processor $processor, int $attachment_id ): void {
		$medium_url = wp_get_attachment_image_url( $attachment_id, ImageSizes::MEDIUM );

		if ( \is_string( $medium_url ) && $medium_url !== '' ) {
			$processor->set_attribute( 'src', $medium_url );
		}

		$srcset = self::build_srcset( $attachment_id );

		if ( $srcset !== '' ) {
			$processor->set_attribute( 'srcset', $srcset );
			$processor->set_attribute( 'sizes', '(max-width: 600px) 100vw, 1000px' );
		}
	}

	/**
	 * Records the href, dimensions, and EXIF caption for an attachment ahead of the wrapping pass.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private static function record_anchor_data( int $attachment_id ): void {
		$large_url = wp_get_attachment_image_url( $attachment_id, ImageSizes::LARGE );
		$large_src = wp_get_attachment_image_src( $attachment_id, ImageSizes::LARGE );
		$metadata  = wp_get_attachment_metadata( $attachment_id );

		self::$anchor_data[ $attachment_id ] = [
			'href'   => \is_string( $large_url ) && $large_url !== '' ? $large_url : '',
			'width'  => \is_array( $large_src ) ? $large_src[1] : 0,
			'height' => \is_array( $large_src ) ? $large_src[2] : 0,
			'exif'   => Exif::format( \is_array( $metadata ) ? $metadata['image_meta'] : [] ),
		];
	}

	/**
	 * Builds the srcset string from the three Apermo Gallery sizes.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	private static function build_srcset( int $attachment_id ): string {
		$parts = [];

		foreach ( self::SRCSET_WIDTHS as $size => $width ) {
			$url = wp_get_attachment_image_url( $attachment_id, $size );
			if ( \is_string( $url ) && $url !== '' ) {
				$parts[] = $url . ' ' . $width . 'w';
			}
		}

		return \implode( ', ', $parts );
	}

	/**
	 * Wraps every `<img class="...wp-image-NNN...">` with an anchor to its large URL.
	 *
	 * @param string $html The HTML with rewritten img attributes.
	 *
	 * @return string
	 */
	private static function wrap_images_with_anchors( string $html ): string {
		if ( self::$anchor_data === [] ) {
			return $html;
		}

		$result = \preg_replace_callback(
			'/<img\b[^>]*\bclass="[^"]*\bwp-image-(\d+)\b[^"]*"[^>]*>/',
			[ self::class, 'wrap_single_image' ],
			$html,
		);

		return \is_string( $result ) ? $result : $html;
	}

	/**
	 * Renders a single `<img>` wrapped in an anchor carrying PhotoSwipe + EXIF data.
	 *
	 * @param array<int, string> $match The preg_replace_callback match (full tag at 0, attachment ID at 1).
	 *
	 * @return string
	 */
	private static function wrap_single_image( array $match ): string {
		$attachment_id = (int) $match[1];

		if ( ! isset( self::$anchor_data[ $attachment_id ] ) ) {
			return $match[0];
		}

		$data = self::$anchor_data[ $attachment_id ];

		return \sprintf(
			'<a href="%s" data-pswp-width="%d" data-pswp-height="%d" data-apermo-exif="%s">%s</a>',
			esc_url( $data['href'] ),
			$data['width'],
			$data['height'],
			esc_attr( $data['exif'] ),
			$match[0],
		);
	}

	/**
	 * Adds the `apermo-gallery` class to the gallery wrapper so the lightbox bootstrap can find it.
	 *
	 * @param string $html The HTML with anchors in place.
	 *
	 * @return string
	 */
	private static function stamp_container_class( string $html ): string {
		$processor = new WP_HTML_Tag_Processor( $html );

		$found = $processor->next_tag(
			[
				'tag_name'   => 'figure',
				'class_name' => 'wp-block-gallery',
			],
		);

		if ( $found ) {
			$processor->add_class( 'apermo-gallery' );
		}

		return $processor->get_updated_html();
	}
}
