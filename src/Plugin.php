<?php

declare(strict_types=1);

namespace Apermo\Gallery;

/**
 * Wires the plugin's components from a single registry.
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
		Lightbox::register();
	}
}
