<?php

declare(strict_types=1);

namespace Apermo\Gallery\Tests\Unit;

use Apermo\Gallery\Render;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the pass-through paths of the Render filter.
 *
 * The full rewrite path requires WP_HTML_Tag_Processor and gets covered by the
 * Playwright E2E suite. Here we verify the filter never touches output that
 * isn't an opted-in core/gallery.
 */
class RenderTest extends TestCase {

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
	 * Verifies register hooks the filter on render_block.
	 *
	 * @return void
	 */
	public function test_register_hooks_render_block(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'render_block', [ Render::class, 'filter' ], 10, 2 );

		Render::register();
	}

	/**
	 * Verifies blocks other than core/gallery pass through unchanged.
	 *
	 * @return void
	 */
	public function test_other_blocks_pass_through(): void {
		$content = '<p>Hello</p>';
		$block   = [ 'blockName' => 'core/paragraph', 'attrs' => [] ];

		$this->assertSame( $content, Render::filter( $content, $block ) );
	}

	/**
	 * Verifies core/gallery without the opt-in class passes through unchanged.
	 *
	 * @return void
	 */
	public function test_gallery_without_optin_class_passes_through(): void {
		$content = '<figure class="wp-block-gallery"></figure>';
		$block   = [ 'blockName' => 'core/gallery', 'attrs' => [ 'className' => 'is-cropped' ] ];

		$this->assertSame( $content, Render::filter( $content, $block ) );
	}
}
