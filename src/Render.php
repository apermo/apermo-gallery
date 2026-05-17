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
		if ( 'core/gallery' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		$class_name = (string) ( $block['attrs']['className'] ?? '' );

		if ( ! str_contains( $class_name, 'is-apermo-gallery' ) ) {
			return $block_content;
		}

		$wraps    = self::rewrite_image_attributes( $block_content );
		$wrapped  = self::wrap_images_with_anchors( $wraps['html'], $wraps['data'] );
		$finished = self::stamp_container_class( $wrapped );

		return $finished;
	}

	/**
	 * Rewrites every `<img>`'s src and srcset to the Apermo Gallery sizes.
	 *
	 * Returns the updated HTML alongside a map keyed by attachment ID containing
	 * the data needed by `wrap_images_with_anchors` (large URL + EXIF caption).
	 *
	 * @param string $html The original block HTML.
	 *
	 * @return array{html: string, data: array<int, array{href: string, exif: string}>}
	 */
	private static function rewrite_image_attributes( string $html ): array {
		$processor = new WP_HTML_Tag_Processor( $html );
		$data      = [];

		while ( $processor->next_tag( 'img' ) ) {
			$class_attr = (string) $processor->get_attribute( 'class' );

			if ( 1 !== preg_match( '/\bwp-image-(\d+)\b/', $class_attr, $matches ) ) {
				continue;
			}

			$attachment_id = (int) $matches[1];

			$medium_url = wp_get_attachment_image_url( $attachment_id, ImageSizes::MEDIUM );
			$large_url  = wp_get_attachment_image_url( $attachment_id, ImageSizes::LARGE );

			if ( is_string( $medium_url ) && '' !== $medium_url ) {
				$processor->set_attribute( 'src', $medium_url );
			}

			$srcset = self::build_srcset( $attachment_id );

			if ( '' !== $srcset ) {
				$processor->set_attribute( 'srcset', $srcset );
				$processor->set_attribute( 'sizes', '(max-width: 600px) 100vw, 1000px' );
			}

			$metadata   = wp_get_attachment_metadata( $attachment_id );
			$image_meta = is_array( $metadata ) && isset( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] )
				? $metadata['image_meta']
				: [];

			$data[ $attachment_id ] = [
				'href' => is_string( $large_url ) && '' !== $large_url ? $large_url : (string) $medium_url,
				'exif' => Exif::format( $image_meta ),
			];

			AttachmentFlag::flag( $attachment_id );
		}

		return [
			'html' => $processor->get_updated_html(),
			'data' => $data,
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
			if ( is_string( $url ) && '' !== $url ) {
				$parts[] = $url . ' ' . $width . 'w';
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Wraps every `<img class="...wp-image-NNN...">` with an anchor to its large URL.
	 *
	 * @param string                                                  $html The HTML with rewritten img attributes.
	 * @param array<int, array{href: string, exif: string}>           $data Per-attachment href + EXIF.
	 *
	 * @return string
	 */
	private static function wrap_images_with_anchors( string $html, array $data ): string {
		if ( [] === $data ) {
			return $html;
		}

		$result = preg_replace_callback(
			'/<img\b[^>]*\bclass="[^"]*\bwp-image-(\d+)\b[^"]*"[^>]*>/',
			static function ( array $match ) use ( $data ): string {
				$attachment_id = (int) $match[1];

				if ( ! isset( $data[ $attachment_id ] ) ) {
					return $match[0];
				}

				return sprintf(
					'<a href="%s" data-apermo-exif="%s">%s</a>',
					esc_url( $data[ $attachment_id ]['href'] ),
					esc_attr( $data[ $attachment_id ]['exif'] ),
					$match[0],
				);
			},
			$html,
		);

		return is_string( $result ) ? $result : $html;
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

		if ( $processor->next_tag( [ 'tag_name' => 'figure', 'class_name' => 'wp-block-gallery' ] ) ) {
			$processor->add_class( 'apermo-gallery' );
		}

		return $processor->get_updated_html();
	}
}
