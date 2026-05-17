<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Formats raw `_wp_attachment_metadata['image_meta']` into a human caption line.
 */
class Exif {

	/**
	 * Builds the caption line from raw image_meta. Returns an empty string when nothing is present.
	 *
	 * @param array<string, mixed> $image_meta The image_meta array from _wp_attachment_metadata.
	 *
	 * @return string
	 */
	public static function format( array $image_meta ): string {
		$parts = [];

		$camera = trim( (string) ( $image_meta['camera'] ?? '' ) );
		if ( '' !== $camera ) {
			$parts[] = $camera;
		}

		$aperture = (float) ( $image_meta['aperture'] ?? 0 );
		if ( $aperture > 0 ) {
			$parts[] = 'f/' . self::trim_decimals( $aperture );
		}

		$shutter = (float) ( $image_meta['shutter_speed'] ?? 0 );
		if ( $shutter > 0 ) {
			$parts[] = self::format_shutter( $shutter );
		}

		$focal = (float) ( $image_meta['focal_length'] ?? 0 );
		if ( $focal > 0 ) {
			$parts[] = self::trim_decimals( $focal ) . ' mm';
		}

		$iso = (int) ( $image_meta['iso'] ?? 0 );
		if ( $iso > 0 ) {
			$parts[] = 'ISO ' . $iso;
		}

		$timestamp = (int) ( $image_meta['created_timestamp'] ?? 0 );
		if ( $timestamp > 0 ) {
			$parts[] = date_i18n( 'Y-m-d', $timestamp );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Renders a shutter speed as "1/250 s" below one second or "2.5 s" at or above one second.
	 *
	 * @param float $seconds The shutter speed in seconds.
	 *
	 * @return string
	 */
	private static function format_shutter( float $seconds ): string {
		if ( $seconds >= 1.0 ) {
			return self::trim_decimals( $seconds ) . ' s';
		}

		$denominator = (int) round( 1 / $seconds );

		return '1/' . $denominator . ' s';
	}

	/**
	 * Renders a float with up to one decimal, stripping trailing zeros and the dot.
	 *
	 * @param float $value The value to format.
	 *
	 * @return string
	 */
	private static function trim_decimals( float $value ): string {
		return rtrim( rtrim( number_format( $value, 1, '.', '' ), '0' ), '.' );
	}
}
