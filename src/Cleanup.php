<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Removes non-whitelisted derivative files when an attachment is flagged for a gallery.
 *
 * Listens on `apermo_gallery_flag_set` so the cleanup logic stays decoupled from how
 * the flag gets set (manual bulk action, auto-flag during gallery render, future paths).
 */
class Cleanup {

	/**
	 * Registers the WordPress hooks owned by this component.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'apermo_gallery_flag_set', [ self::class, 'cleanup' ] );
	}

	/**
	 * Deletes derivative files outside the whitelist and rewrites the size metadata.
	 *
	 * Idempotent: a second invocation on an already-cleaned attachment is a no-op.
	 *
	 * @param int $attachment_id The attachment whose derivatives should be pruned.
	 *
	 * @return void
	 */
	public static function cleanup( int $attachment_id ): void {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return;
		}

		$original = get_attached_file( $attachment_id );

		if ( ! is_string( $original ) || '' === $original ) {
			return;
		}

		$directory = trailingslashit( dirname( $original ) );
		$changed   = false;

		foreach ( $metadata['sizes'] as $size => $info ) {
			if ( in_array( $size, ImageSizes::WHITELIST, true ) ) {
				continue;
			}

			if ( is_array( $info ) && ! empty( $info['file'] ) && is_string( $info['file'] ) ) {
				$path = $directory . $info['file'];
				if ( file_exists( $path ) ) {
					wp_delete_file( $path );
				}
			}

			unset( $metadata['sizes'][ $size ] );
			$changed = true;
		}

		if ( $changed ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}
}
