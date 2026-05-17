<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Central registry that wires the plugin's components.
 */
class Plugin {

	/**
	 * Boots every component that has hooks to register.
	 *
	 * @return void
	 */
	public static function boot(): void {
		ImageSizes::register();
		AttachmentFlag::register();
		Cleanup::register();
		BlockVariation::register();
		Render::register();
	}
}
