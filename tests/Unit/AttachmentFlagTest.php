<?php

declare(strict_types=1);

namespace Apermo\Gallery\Tests\Unit;

use Apermo\Gallery\AttachmentFlag;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the AttachmentFlag class.
 */
class AttachmentFlagTest extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Verifies register hooks both bulk-action filters.
	 *
	 * @return void
	 */
	public function test_register_hooks_bulk_action_filters(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'bulk_actions-upload', [ AttachmentFlag::class, 'filter_bulk_actions' ] );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'handle_bulk_actions-upload', [ AttachmentFlag::class, 'handle_bulk_action' ], 10, 3 );

		AttachmentFlag::register();
	}

	/**
	 * Verifies is_flagged returns true when meta is truthy.
	 *
	 * @return void
	 */
	public function test_is_flagged_returns_true_when_meta_present(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, '_apermo_gallery_image', true )
			->andReturn( '1' );

		$this->assertTrue( AttachmentFlag::is_flagged( 42 ) );
	}

	/**
	 * Verifies is_flagged returns false when meta is empty.
	 *
	 * @return void
	 */
	public function test_is_flagged_returns_false_when_meta_absent(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, '_apermo_gallery_image', true )
			->andReturn( '' );

		$this->assertFalse( AttachmentFlag::is_flagged( 42 ) );
	}

	/**
	 * Verifies flag stores meta and fires the flag-set action on first call.
	 *
	 * @return void
	 */
	public function test_flag_stores_meta_and_fires_action(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, '_apermo_gallery_image', true )
			->andReturn( '' );

		Functions\expect( 'update_post_meta' )
			->once()
			->with( 42, '_apermo_gallery_image', 1 );

		Functions\expect( 'do_action' )
			->once()
			->with( 'apermo_gallery_flag_set', 42 );

		AttachmentFlag::flag( 42 );
	}

	/**
	 * Verifies flag is a no-op when the attachment is already flagged.
	 *
	 * @return void
	 */
	public function test_flag_skips_when_already_flagged(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, '_apermo_gallery_image', true )
			->andReturn( '1' );

		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'do_action' )->never();

		AttachmentFlag::flag( 42 );
	}

	/**
	 * Verifies the bulk-action filter adds our action with a translated label.
	 *
	 * @return void
	 */
	public function test_filter_bulk_actions_adds_our_action(): void {
		Functions\when( '__' )->returnArg();

		$result = AttachmentFlag::filter_bulk_actions( [ 'delete' => 'Delete' ] );

		$this->assertSame(
			[
				'delete'                  => 'Delete',
				'apermo_gallery_flag'     => 'Mark as gallery image',
			],
			$result,
		);
	}

	/**
	 * Verifies handle_bulk_action flags every selected ID and rewrites the sendback URL.
	 *
	 * @return void
	 */
	public function test_handle_bulk_action_flags_selected_ids(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'add_query_arg' )->alias(
			static fn ( string $key, int $value, string $url ): string => $url . '?' . $key . '=' . $value,
		);

		$result = AttachmentFlag::handle_bulk_action( 'https://example.tld/upload', 'apermo_gallery_flag', [ 1, 2, 3 ] );

		$this->assertSame( 'https://example.tld/upload?apermo_gallery_flagged=3', $result );
	}

	/**
	 * Verifies handle_bulk_action returns the sendback unchanged for other actions.
	 *
	 * @return void
	 */
	public function test_handle_bulk_action_passes_through_unknown_actions(): void {
		Functions\expect( 'update_post_meta' )->never();

		$result = AttachmentFlag::handle_bulk_action( 'https://example.tld/upload', 'delete', [ 1, 2 ] );

		$this->assertSame( 'https://example.tld/upload', $result );
	}
}
