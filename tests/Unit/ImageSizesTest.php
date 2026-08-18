<?php

declare(strict_types=1);

namespace Apermo\Gallery\Tests\Unit;

use Apermo\Gallery\ImageSizes;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ImageSizes class.
 */
class ImageSizesTest extends TestCase {

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
	 * Verifies register hooks add_sizes on after_setup_theme.
	 *
	 * @return void
	 */
	public function test_register_hooks_after_setup_theme(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'after_setup_theme', [ ImageSizes::class, 'add_sizes' ] );

		ImageSizes::register();
	}

	/**
	 * Verifies add_sizes registers the three custom sizes with the right dimensions.
	 *
	 * @return void
	 */
	public function test_add_sizes_registers_three_sizes(): void {
		Functions\expect( 'add_image_size' )
			->once()
			->with( 'apermo-gallery-thumb', 400, 400, true );

		Functions\expect( 'add_image_size' )
			->once()
			->with( 'apermo-gallery-medium', 1000, 1000, false );

		Functions\expect( 'add_image_size' )
			->once()
			->with( 'apermo-gallery-large', 1600, 1600, false );

		ImageSizes::add_sizes();
	}

	/**
	 * Verifies the whitelist contains exactly the three custom sizes.
	 *
	 * @return void
	 */
	public function test_whitelist_contains_three_sizes(): void {
		$this->assertSame(
			[
				'apermo-gallery-thumb',
				'apermo-gallery-medium',
				'apermo-gallery-large',
			],
			ImageSizes::WHITELIST,
		);
	}
}
