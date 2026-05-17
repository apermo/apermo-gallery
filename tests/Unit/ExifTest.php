<?php

declare(strict_types=1);

namespace Apermo\Gallery\Tests\Unit;

use Apermo\Gallery\Exif;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Exif formatter.
 */
class ExifTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs date_i18n to a deterministic gmdate.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'date_i18n' )->alias(
			static fn ( string $format, int $timestamp ): string => \gmdate( $format, $timestamp ),
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
	 * Verifies a full image_meta produces the expected caption.
	 *
	 * @return void
	 */
	public function test_full_meta_renders_full_caption(): void {
		$meta = [
			'camera'            => 'Canon EOS R5',
			'aperture'          => '2.8',
			'shutter_speed'     => '0.004',
			'focal_length'      => '35',
			'iso'               => '400',
			'created_timestamp' => 1_773_360_000,
		];

		$this->assertSame(
			'Canon EOS R5 · f/2.8 · 1/250 s · 35 mm · ISO 400 · 2026-03-12',
			Exif::format( $meta ),
		);
	}

	/**
	 * Verifies an empty image_meta returns an empty string.
	 *
	 * @return void
	 */
	public function test_empty_meta_returns_empty_string(): void {
		$this->assertSame( '', Exif::format( [] ) );
	}

	/**
	 * Verifies zero-valued numeric fields are omitted rather than rendered as "f/0", "ISO 0", etc.
	 *
	 * @return void
	 */
	public function test_zero_values_are_omitted(): void {
		$meta = [
			'camera'            => 'Canon EOS R5',
			'aperture'          => '0',
			'shutter_speed'     => '0',
			'focal_length'      => '0',
			'iso'               => '0',
			'created_timestamp' => 0,
		];

		$this->assertSame( 'Canon EOS R5', Exif::format( $meta ) );
	}

	/**
	 * Verifies whole-number apertures and focal lengths drop their trailing decimals.
	 *
	 * @return void
	 */
	public function test_whole_numbers_drop_trailing_decimals(): void {
		$meta = [
			'aperture'     => '8.0',
			'focal_length' => '50.0',
		];

		$this->assertSame( 'f/8 · 50 mm', Exif::format( $meta ) );
	}

	/**
	 * Verifies shutter speeds at or above one second render as decimal seconds.
	 *
	 * @return void
	 */
	public function test_long_exposure_renders_as_decimal_seconds(): void {
		$meta = [
			'shutter_speed' => '2.5',
		];

		$this->assertSame( '2.5 s', Exif::format( $meta ) );
	}

	/**
	 * Verifies partial meta with only camera and ISO renders both fields.
	 *
	 * @return void
	 */
	public function test_partial_meta_renders_present_fields_only(): void {
		$meta = [
			'camera' => 'Fujifilm X-T5',
			'iso'    => '800',
		];

		$this->assertSame( 'Fujifilm X-T5 · ISO 800', Exif::format( $meta ) );
	}
}
