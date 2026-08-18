<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Registers the "Photo Gallery" variation of core/gallery in the block editor.
 *
 * The variation applies the `is-apermo-gallery` class to the gallery wrapper, which
 * the server-side render filter keys off to rewrite the gallery output.
 */
class BlockVariation {

	/**
	 * Registers the WordPress hooks owned by this component.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueues the compiled editor script that registers the variation.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$main_file = Main::file();

		if ( $main_file === '' ) {
			return;
		}

		$asset_path = \dirname( $main_file ) . '/assets/build/editor/variation.asset.php';

		if ( ! \file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			'apermo-gallery-variation',
			plugins_url( 'assets/build/editor/variation.js', $main_file ),
			\is_array( $asset ) && isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : [],
			\is_array( $asset ) && isset( $asset['version'] ) ? (string) $asset['version'] : Main::VERSION,
			true,
		);
	}
}
