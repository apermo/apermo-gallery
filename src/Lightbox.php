<?php

declare(strict_types=1);

namespace Apermo\Gallery;

use WP_Post;

/**
 * Enqueues the PhotoSwipe bootstrap on singulars that contain an opted-in gallery.
 *
 * Detection is a cheap substring check on the post content for the
 * `is-apermo-gallery` marker, which the block variation always writes.
 */
class Lightbox {

	/**
	 * Registers the WordPress hooks owned by this component.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Conditionally enqueues the lightbox bundle and its stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || ! str_contains( $post->post_content, 'is-apermo-gallery' ) ) {
			return;
		}

		$main_file = Main::file();

		if ( '' === $main_file ) {
			return;
		}

		$plugin_dir = dirname( $main_file );
		$asset_path = $plugin_dir . '/assets/build/frontend/lightbox.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset   = include $asset_path;
		$version = is_array( $asset ) && isset( $asset['version'] ) ? (string) $asset['version'] : Main::VERSION;
		$deps    = is_array( $asset ) && isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : [];

		wp_enqueue_script(
			'apermo-gallery-lightbox',
			plugins_url( 'assets/build/frontend/lightbox.js', $main_file ),
			$deps,
			$version,
			true,
		);

		$style_path = $plugin_dir . '/assets/build/frontend/lightbox.css';

		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'apermo-gallery-lightbox',
				plugins_url( 'assets/build/frontend/lightbox.css', $main_file ),
				[],
				$version,
			);
		}
	}
}
