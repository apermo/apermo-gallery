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

		if ( ! \is_array( $metadata ) ) {
			return;
		}

		$sizes = $metadata['sizes'];

		if ( $sizes === [] ) {
			return;
		}

		$original = get_attached_file( $attachment_id );

		if ( ! \is_string( $original ) || $original === '' ) {
			return;
		}

		$kept = self::prune_sizes( $sizes, trailingslashit( \dirname( $original ) ) );

		if ( \count( $kept ) === \count( $sizes ) ) {
			return;
		}

		$metadata['sizes'] = $kept;

		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Returns the subset of `$sizes` that belong to the whitelist, deleting the rest from disk.
	 *
	 * @param array<string, mixed> $sizes     The original `sizes` map from attachment metadata.
	 * @param string               $directory Absolute path to the attachment's upload directory (trailing slash included).
	 *
	 * @return array<string, mixed>
	 */
	private static function prune_sizes( array $sizes, string $directory ): array {
		$kept = [];

		foreach ( $sizes as $size => $info ) {
			if ( \in_array( $size, ImageSizes::WHITELIST, true ) ) {
				$kept[ $size ] = $info;
				continue;
			}

			self::delete_size_file( $info, $directory );
		}

		return $kept;
	}

	/**
	 * Deletes the file referenced by a single size record, when one is present.
	 *
	 * @param mixed  $info      The size record from attachment metadata.
	 * @param string $directory Absolute path to the attachment's upload directory.
	 *
	 * @return void
	 */
	private static function delete_size_file( mixed $info, string $directory ): void {
		if ( ! \is_array( $info ) ) {
			return;
		}

		$file = $info['file'] ?? '';

		if ( ! \is_string( $file ) || $file === '' ) {
			return;
		}

		wp_delete_file( $directory . $file );
	}
}
