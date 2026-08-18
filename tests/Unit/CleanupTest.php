<?php

declare(strict_types=1);

namespace Apermo\Gallery\Tests\Unit;

use Apermo\Gallery\Cleanup;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Cleanup class.
 */
class CleanupTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs trailingslashit to a deterministic implementation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'trailingslashit' )->alias(
			static fn ( string $path ): string => \rtrim( $path, '/' ) . '/',
		);
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Verifies register hooks cleanup on apermo_gallery_flag_set.
	 *
	 * @return void
	 */
	public function test_register_hooks_flag_set_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'apermo_gallery_flag_set', [ Cleanup::class, 'cleanup' ] );

		Cleanup::register();
	}

	/**
	 * Verifies cleanup is a no-op when there is no attachment metadata.
	 *
	 * @return void
	 */
	public function test_cleanup_skips_when_metadata_missing(): void {
		Functions\expect( 'wp_get_attachment_metadata' )->once()->andReturn( false );
		Functions\expect( 'wp_delete_file' )->never();
		Functions\expect( 'wp_update_attachment_metadata' )->never();

		Cleanup::cleanup( 42 );
	}

	/**
	 * Verifies cleanup is a no-op when only whitelisted sizes are present.
	 *
	 * @return void
	 */
	public function test_cleanup_skips_when_only_whitelisted_sizes_present(): void {
		Functions\expect( 'wp_get_attachment_metadata' )->once()->andReturn(
			[
				'sizes' => [
					'apermo-gallery-thumb'  => [ 'file' => 'photo-400x400.jpg' ],
					'apermo-gallery-medium' => [ 'file' => 'photo-1000.jpg' ],
					'apermo-gallery-large'  => [ 'file' => 'photo-1600.jpg' ],
				],
			],
		);
		Functions\expect( 'get_attached_file' )->once()->andReturn( '/uploads/2026/05/photo.jpg' );
		Functions\expect( 'wp_delete_file' )->never();
		Functions\expect( 'wp_update_attachment_metadata' )->never();

		Cleanup::cleanup( 42 );
	}

	/**
	 * Verifies cleanup deletes non-whitelisted files and rewrites size metadata.
	 *
	 * @return void
	 */
	public function test_cleanup_deletes_non_whitelisted_files_and_updates_meta(): void {
		Functions\expect( 'wp_get_attachment_metadata' )->once()->andReturn(
			[
				'width' => 4000,
				'sizes' => [
					'thumbnail'             => [ 'file' => 'photo-150x150.jpg' ],
					'medium'                => [ 'file' => 'photo-300x200.jpg' ],
					'large'                 => [ 'file' => 'photo-1024x683.jpg' ],
					'apermo-gallery-thumb'  => [ 'file' => 'photo-400x400.jpg' ],
					'apermo-gallery-medium' => [ 'file' => 'photo-1000.jpg' ],
					'apermo-gallery-large'  => [ 'file' => 'photo-1600.jpg' ],
				],
			],
		);
		Functions\expect( 'get_attached_file' )->once()->andReturn( '/uploads/2026/05/photo.jpg' );

		Functions\expect( 'wp_delete_file' )->once()->with( '/uploads/2026/05/photo-150x150.jpg' );
		Functions\expect( 'wp_delete_file' )->once()->with( '/uploads/2026/05/photo-300x200.jpg' );
		Functions\expect( 'wp_delete_file' )->once()->with( '/uploads/2026/05/photo-1024x683.jpg' );

		Functions\expect( 'wp_update_attachment_metadata' )
			->once()
			->with(
				42,
				[
					'width' => 4000,
					'sizes' => [
						'apermo-gallery-thumb'  => [ 'file' => 'photo-400x400.jpg' ],
						'apermo-gallery-medium' => [ 'file' => 'photo-1000.jpg' ],
						'apermo-gallery-large'  => [ 'file' => 'photo-1600.jpg' ],
					],
				],
			);

		Cleanup::cleanup( 42 );
	}

	/**
	 * Verifies cleanup tolerates a missing 'file' entry inside a size record.
	 *
	 * @return void
	 */
	public function test_cleanup_handles_malformed_size_records(): void {
		Functions\expect( 'wp_get_attachment_metadata' )->once()->andReturn(
			[
				'sizes' => [
					'thumbnail'            => [ 'width' => 150 ],
					'apermo-gallery-thumb' => [ 'file' => 'photo-400x400.jpg' ],
				],
			],
		);
		Functions\expect( 'get_attached_file' )->once()->andReturn( '/uploads/2026/05/photo.jpg' );
		Functions\expect( 'wp_delete_file' )->never();
		Functions\expect( 'wp_update_attachment_metadata' )
			->once()
			->with(
				42,
				[
					'sizes' => [
						'apermo-gallery-thumb' => [ 'file' => 'photo-400x400.jpg' ],
					],
				],
			);

		Cleanup::cleanup( 42 );
	}
}
