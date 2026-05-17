# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Photo Gallery` block variation of `core/gallery` (applies the
  `is-apermo-gallery` className).
- Three custom image sizes for gallery photos: `apermo-gallery-thumb`
  (400×400 hard-cropped), `apermo-gallery-medium` (1000 longest edge),
  `apermo-gallery-large` (1600 longest edge).
- Server-side rewrite of opted-in gallery markup: each `<img>` is
  resized via the custom sizes, wrapped in an `<a>` pointing at the
  large URL with `data-pswp-*` dimensions and a formatted
  `data-apermo-exif` caption.
- PhotoSwipe v5 lightbox with filmstrip and a two-line caption
  (post caption / alt + EXIF line).
- EXIF formatter rendering `<camera> · f/X · 1/Y s · Z mm · ISO N ·
  YYYY-MM-DD` from `_wp_attachment_metadata['image_meta']`.
- `_apermo_gallery_image` attachment flag, set via the Media Library
  bulk action **Mark as gallery image** or auto-set the first time an
  attachment renders inside an opted-in gallery.
- Derivative cleanup that deletes non-whitelisted size files and
  rewrites `_wp_attachment_metadata['sizes']` whenever the flag is set.
- Plugin bootstrap (`Plugin` registry called from `Main::boot`) and a
  Playwright spec (`e2e/gallery.spec.js`, opt-in via
  `APERMO_GALLERY_TEST_SLUG`) covering rewritten markup, lightbox
  caption, and axe-core.
