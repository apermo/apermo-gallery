<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Registers the three custom image sizes used by Apermo Gallery photos.
 */
class ImageSizes {

	public const THUMB  = 'apermo-gallery-thumb';
	public const MEDIUM = 'apermo-gallery-medium';
	public const LARGE  = 'apermo-gallery-large';

	/**
	 * Whitelist of sizes that gallery attachments are allowed to keep on disk.
	 *
	 * @var string[]
	 */
	public const WHITELIST = [
		self::THUMB,
		self::MEDIUM,
		self::LARGE,
	];

	/**
	 * Registers the WordPress hooks owned by this component.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'after_setup_theme', [ self::class, 'add_sizes' ] );
	}

	/**
	 * Adds the three custom image sizes.
	 *
	 * @return void
	 */
	public static function add_sizes(): void {
		add_image_size( self::THUMB, 400, 400, true );
		add_image_size( self::MEDIUM, 1000, 1000, false );
		add_image_size( self::LARGE, 1600, 1600, false );
	}
}
