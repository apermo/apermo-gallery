# Apermo Gallery — Planning Notes

Seed document for the planning session. Captures intent, hard constraints, and
the open design questions to resolve before any code is written. Once decisions
land, this file is replaced by a concrete spec (or rolled into `README.md` /
`CLAUDE.md`).

## Intent

A minimalistic WordPress gallery plugin for publishing photo posts on
apermo.de in a Flickr-like style: grid of photos that opens into a lightbox
showing the full image, its EXIF data, and a thumbnail strip of the other
images in the same gallery.

The plugin reuses the WordPress core Gallery block instead of shipping a
custom block. No admin sprawl, no CPT, no albums — a regular post or page
with a gallery block is the unit of work.

## Hard constraints

1. **Reuse `core/gallery`.** No new gallery block from scratch. Opt-in
   behavior via a block style or variation, with server-side render
   filtering for the gallery output.
2. **Skip the WordPress thumbnail explosion for gallery photos.** Photos
   uploaded for galleries must not generate the full set of intermediate
   sizes (`thumbnail`, `medium`, `medium_large`, `large`, plus every
   theme/plugin-registered size). Keep at most two derivatives plus the
   original.
3. **Minimalistic.** No settings page in v0.1. Sensible defaults only.

## Out of scope (for v0.1)

- Albums, taxonomies, custom post types
- WP.org distribution (private apermo.de use first; revisit later)
- On-the-fly resizing / CDN integration (later, if needed)
- Flickr / SmugMug / external photo source import

---

## Open design questions

### Q1 — How do we skip image-size generation for gallery photos?

Candidates:

- **A. Per-attachment opt-in flag.** Attachment meta (e.g.
  `_apermo_gallery_image = 1`) marks photos intended for galleries.
  `intermediate_image_sizes_advanced` filter returns only our two custom
  sizes for marked attachments and strips the rest.
- **B. Site-wide policy.** Globally disable default sizes. Rejected — too
  invasive for non-gallery media.
- **C. Block-driven auto-flag.** On post save, parse blocks and flag every
  attachment inside a `core/gallery` for future reprocessing. Works going
  forward, doesn't help retroactively (sizes are generated at upload time).
- **D. Hybrid:** A "Gallery upload" affordance (block sidebar button or
  custom media-library filter) sets the flag *before* derivatives are
  generated. Server-side fallback also flags attachments that show up in a
  gallery on save.

Recommendation: **A + D combined.** Two custom sizes — `apermo-gallery-thumb`
(400px square, soft-cropped) and `apermo-gallery-large` (1600px longest
edge). Original kept untouched for download / max-zoom in lightbox.

### Q2 — How do we hook into `core/gallery` without forking it?

Candidates:

- **A. Block style** (`is-style-apermo-gallery`). Pure PHP registration, no
  JS bundle, opt-in per gallery via the style picker. Server-side
  `render_block` filter detects the class on the wrapper and rewrites
  output.
- **B. Block variation.** Slightly richer editor UX (named "Photo Gallery"
  in the inserter) but requires a JS bundle just to register the variation.
- **C. Render-time enhancement of every `core/gallery`.** Zero editor
  affordance, no opt-out — too magical.
- **D. New block extending core.** Heaviest; rejected.

Recommendation: **A.** Smallest surface. Upgrade to B later if the editor
ergonomics matter.

### Q3 — Lightbox

Candidates:

- **A. WordPress core's native image lightbox** (`core/image` `behavior:
  lightbox`, 6.4+). Free, native, zero JS to ship. No EXIF, no filmstrip.
- **B. PhotoSwipe v5.** ~30KB, no jQuery, well maintained. Filmstrip,
  zoom, captions. Inject EXIF lines + filmstrip data server-side as `data-`
  attributes; small bootstrap script wires it up.
- **C. Custom mini-lightbox.** Reinventing the wheel for no reason.

Recommendation: **B.** The EXIF + filmstrip requirement makes A
insufficient. PhotoSwipe is the smallest dependency that delivers both.

### Q4 — EXIF source & formatting

- WordPress already extracts camera, aperture, shutter speed, focal length,
  ISO, captured timestamp, and GPS into
  `_wp_attachment_metadata['image_meta']` on upload. No extra parsing
  needed.
- A small formatter helper turns the raw values into human strings:
  `f/2.8`, `1/250 s`, `35 mm`, `ISO 400`, capture date in site timezone.
- GPS deferred to v0.2.

### Q5 — Scope cut for v0.1

- Block style `is-style-apermo-gallery` registered on `core/gallery`.
- Two custom image sizes; `intermediate_image_sizes_advanced` filter
  strips the rest for flagged attachments.
- Attachment-meta flag set via a media-library bulk action and (fallback)
  auto-flag on post save when an attachment first appears inside a styled
  gallery.
- Render filter rewrites the gallery's inner `core/image` blocks to use
  the two custom sizes and adds EXIF data to each image as
  `data-apermo-exif="…"`.
- PhotoSwipe bootstrap reads those data attributes, builds the lightbox
  with a filmstrip and an EXIF caption.
- No admin settings page. No options.

---

## Architecture sketch (post-`setup.sh`, plugin mode)

```
src/
  Plugin.php              # bootstrap, hooks wiring
  ImageSizes.php          # add_image_size + intermediate_image_sizes_advanced
  BlockStyle.php          # register_block_style + render_block filter
  Exif.php                # format image_meta into human strings
  Lightbox.php            # enqueue PhotoSwipe + bootstrap
  AttachmentFlag.php      # set/read _apermo_gallery_image, bulk action
assets/
  js/lightbox.js          # PhotoSwipe wiring
  css/gallery.css         # minor grid/lightbox tweaks
  vendor/photoswipe/      # vendored (or npm-built — TBD)
tests/
  Unit/ExifTest.php
  Integration/ImageSizesTest.php
e2e/
  gallery.spec.js
```

---

## Decisions deferred to planning session

- Confirm Q1 recommendation (A + D) and the two custom-size dimensions.
- Confirm Q2 (block style) vs. spending the JS bundle on a variation.
- PhotoSwipe distribution: vendored static files vs. npm + build step.
- Retroactive flag UX: bulk action only, or also a per-attachment
  checkbox in the media modal sidebar?
- Naming: `apermo-gallery` everywhere, or shorter slug for runtime
  (e.g., `apg-…`) on data attributes / CSS classes?

---

## Reference

Conversation that triggered this plugin (May 2026) compared third-party
options (Meow Gallery + Meow Lightbox, NextGEN, FooGallery PRO, X3P0:
Media Data). The conclusion was that none was minimalistic enough and
none avoided WP's thumbnail explosion — hence this build.
