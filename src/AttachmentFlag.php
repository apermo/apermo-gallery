<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Marks attachments as gallery photos and exposes a Media Library bulk action.
 *
 * Setting the flag fires the `apermo_gallery_flag_set` action so other components
 * (e.g. derivative cleanup) can react without coupling to this class directly.
 */
class AttachmentFlag {

	public const META_KEY    = '_apermo_gallery_image';
	public const BULK_ACTION = 'apermo_gallery_flag';

	/**
	 * Registers the WordPress hooks owned by this component.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'bulk_actions-upload', [ self::class, 'filter_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-upload', [ self::class, 'handle_bulk_action' ], 10, 3 );
	}

	/**
	 * Returns whether an attachment is flagged as a gallery photo.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public static function is_flagged( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, self::META_KEY, true );
	}

	/**
	 * Sets the flag on an attachment. No-op when the attachment is already flagged.
	 *
	 * Fires `apermo_gallery_flag_set` with the attachment ID when the flag is newly set.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public static function flag( int $attachment_id ): void {
		if ( self::is_flagged( $attachment_id ) ) {
			return;
		}

		update_post_meta( $attachment_id, self::META_KEY, 1 );
		do_action( 'apermo_gallery_flag_set', $attachment_id );
	}

	/**
	 * Adds the "Mark as gallery image" bulk action to the upload list screen.
	 *
	 * @param array<string, string> $actions Existing bulk actions keyed by action name.
	 *
	 * @return array<string, string>
	 */
	public static function filter_bulk_actions( array $actions ): array {
		$actions[ self::BULK_ACTION ] = __( 'Mark as gallery image', 'apermo-gallery' );

		return $actions;
	}

	/**
	 * Flags every selected attachment when the bulk action is invoked.
	 *
	 * @param string $sendback The current redirect URL.
	 * @param string $action   The bulk action being handled.
	 * @param int[]  $ids      The selected attachment IDs.
	 *
	 * @return string
	 */
	public static function handle_bulk_action( string $sendback, string $action, array $ids ): string {
		if ( self::BULK_ACTION !== $action ) {
			return $sendback;
		}

		foreach ( $ids as $id ) {
			self::flag( (int) $id );
		}

		return add_query_arg( 'apermo_gallery_flagged', count( $ids ), $sendback );
	}
}
