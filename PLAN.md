# Apermo Gallery — v0.1 Spec

A minimalistic WordPress gallery plugin for publishing photo posts on
apermo.de in a Flickr-style way: grid of photos that opens into a
lightbox showing the full image, its EXIF data, and a thumbnail strip of
the other images in the same gallery.

The plugin reuses the WordPress core Gallery block instead of shipping a
custom block. No admin sprawl, no CPT, no albums — a regular post or
page with a gallery block is the unit of work.

For the rationale behind each design choice and the alternatives we
rejected, see [`DECISIONS.md`](DECISIONS.md).

## Hard constraints

1. **Reuse `core/gallery`.** No new gallery block from scratch. Opt-in
   via a block variation; server-side render filter rewrites the
   gallery output.
2. **No WP intermediate-size explosion for gallery photos.** At most
   three whitelisted derivatives plus the original.
3. **Minimalistic.** No settings page in v0.1. Sensible defaults only.

## Out of scope (v0.1)

- Albums, taxonomies, custom post types
- WP.org distribution (private apermo.de use first; revisit later)
- On-the-fly resizing / CDN integration
- Flickr / SmugMug / external photo source import
- GPS map, lens, copyright, color-profile display
- WP-CLI commands (a `flag <id...>` command is a v0.2 candidate)

## Locked decisions

| Topic | Decision |
| --- | --- |
| Image-size strategy | **Cleanup-after-the-fact.** Let WP generate normally; on flag-set, delete non-whitelisted derivatives and rewrite `_wp_attachment_metadata['sizes']`. Flag is set via Media Library bulk action **or** auto on `save_post` when an attachment first appears inside a styled gallery. |
| Editor opt-in | Block variation **`Photo Gallery`** of `core/gallery`. Variation applies className `is-apermo-gallery`; render filter keys off this. |
| Lightbox | **PhotoSwipe v5** with filmstrip. EXIF shipped inline as `data-apermo-exif` per item. |
| EXIF caption | Two lines: (1) WP caption (falls back to title/alt); (2) `<camera> · f/X · 1/Y s · Z mm · ISO N · YYYY-MM-DD`. Missing values silently omitted. |
| Custom sizes | `apermo-gallery-thumb` 400×400 soft-cropped · `apermo-gallery-medium` 1000 longest edge · `apermo-gallery-large` 1600 longest edge · original kept untouched. |
| PhotoSwipe distribution | npm + `@wordpress/scripts` build. Source `assets/src/`, output `assets/build/` (committed). |
| Manual flag UX | Media Library list-view bulk action **`Mark as gallery image`** only. No media-modal checkbox. |
| Naming | `apermo-gallery` everywhere — slug, text domain, `is-apermo-gallery` class, `data-apermo-exif` attr, `_apermo_gallery_image` meta key. PHP namespace `Apermo\Gallery`. |

## Component layout

```
src/                                  # PSR-4 (Apermo\Gallery\…)
  Plugin.php                          # bootstrap, hooks wiring
  ImageSizes.php                      # add_image_size for 3 custom sizes
  AttachmentFlag.php                  # read/set _apermo_gallery_image,
                                      # Media Library bulk action
  Cleanup.php                         # delete non-whitelisted derivatives,
                                      # rewrite _wp_attachment_metadata['sizes']
  BlockVariation.php                  # register variation + enqueue editor JS
  Render.php                          # render_block filter for core/gallery
                                      # when is-apermo-gallery class present
  Exif.php                            # format image_meta into human strings
  Lightbox.php                        # enqueue frontend bundle + CSS

assets/
  src/
    editor/variation.js               # registers 'Photo Gallery' variation
    frontend/lightbox.js              # PhotoSwipe wiring (reads data-apermo-exif)
    frontend/gallery.css              # minimal grid + caption styling
  build/                              # wp-scripts output (committed)

tests/
  Unit/ExifTest.php                   # formatter null-safety + locale
  Unit/AttachmentFlagTest.php         # flag set/read + bulk-action handler
  Integration/ImageSizesTest.php      # add_image_size + filter behavior
  Integration/CleanupTest.php         # file deletion + meta rewrite

e2e/
  gallery.spec.js                     # insert variation, view post, open
                                      # lightbox, verify caption + filmstrip
                                      # + axe a11y assertion
```

## Image-size pipeline

1. Upload happens normally — WP generates its full derivative set.
2. Flag is set on the attachment when **either**:
   - The Media Library bulk action `Mark as gallery image` is applied,
     **or**
   - `save_post` fires and the post contains a gallery block with class
     `is-apermo-gallery` referencing that attachment ID for the first
     time.
3. On flag-set, `Cleanup` runs:
   - Reads `_wp_attachment_metadata['sizes']`.
   - For every size **not** in the whitelist
     (`apermo-gallery-thumb`, `apermo-gallery-medium`,
     `apermo-gallery-large`), deletes the file under the attachment's
     upload dir and removes the entry from the meta array.
   - Persists the rewritten metadata.
4. Original file is never touched.
5. Cleanup is idempotent — re-running is a no-op when metadata already
   matches the whitelist.

**Known limitation**: a window exists between upload and flag-set where
full derivatives sit on disk. Acceptable for v0.1.

## Editor opt-in

`BlockVariation` registers a JS bundle via `enqueue_block_editor_assets`.
The bundle calls:

```js
wp.blocks.registerBlockVariation('core/gallery', {
  name: 'apermo-gallery',
  title: 'Photo Gallery',
  isDefault: false,
  attributes: { className: 'is-apermo-gallery' },
  scope: ['inserter'],
});
```

Authors choose `Photo Gallery` in the inserter; the resulting block is a
regular `core/gallery` with `is-apermo-gallery` on the wrapper.

## Server-side rendering

`Render` filters `render_block` for `core/gallery`. When the wrapper
class contains `is-apermo-gallery`:

1. For each inner `core/image`:
   - Resolve the attachment ID.
   - Replace `src` with the `apermo-gallery-medium` URL.
   - Replace `srcset` with `thumb 400w, medium 1000w, large 1600w`.
   - Wrap the `<img>` in an `<a>` pointing to the
     `apermo-gallery-large` URL with `data-apermo-exif="…formatted
     line…"`.
   - Preserve existing `alt` and `loading="lazy"` (WP default).
2. Stamp class `apermo-gallery` on the outer container so the lightbox
   bootstrap can attach with one selector.

EXIF formatting comes from `Exif::format($image_meta)`, which:

- Reads `_wp_attachment_metadata['image_meta']`.
- Concatenates non-empty fields in order:
  `camera · f/{aperture} · 1/{1/shutter} s · {focal} mm · ISO {iso} ·
  {date_i18n(captured_at, site timezone)}`.
- Returns the empty string when nothing is present.

## Lightbox

- `assets/src/frontend/lightbox.js` imports PhotoSwipe v5 +
  PhotoSwipeLightbox.
- One Lightbox instance per `.apermo-gallery` container, gallery
  selector `a[data-apermo-exif]`, child selector `img`.
- `contentLoad` event reads `data-apermo-exif` and renders it as the
  EXIF line. The WP image caption is shown above (sourced from the
  surrounding `<figcaption>` if present, falling back to image title /
  alt).
- Filmstrip enabled via PhotoSwipe's built-in thumbnails plugin.
- Frontend bundle is enqueued only on singulars that contain a styled
  gallery (`has_block`-style check during `wp_enqueue_scripts`).
- Accessibility: PhotoSwipe v5 ships keyboard nav, focus trap, ESC
  close, and ARIA labels. We do not add custom controls.

## Image orientation note

WordPress 5.3+ flattens EXIF orientation into the file during upload
(`wp_maybe_exif_rotate`) and resets the orientation tag. Both the
original and the surviving derivatives are correctly oriented in
browsers. No extra handling needed.

---

## Bootstrapping the repo from the template

The repo currently holds the dual-mode template scaffold. To convert it
into the plugin, run `setup.sh` with the answers below.

### `setup.sh` answers

| Prompt | Answer |
| --- | --- |
| `Project slug (kebab-case, e.g. my-plugin):` | `apermo-gallery` |
| `PHP namespace (e.g. Apermo\\MyPlugin):` | `Apermo\Gallery` |
| `Composer package name [apermo/apermo-gallery]:` | *(accept default)* |
| `Select project mode:` | `1` (plugin) |
| `Publish to WordPress.org? (y/N):` | `N` |
| `Include opt-in confirm-deactivate example? (y/N):` | `N` |
| `Configure GitHub repository? (y/N):` | `Y` *(only if `gh` is authenticated; otherwise `N` and configure later)* |

### What `setup.sh` will remove

- **Plugin mode cleanup**: `style.css`, `functions.php`, `theme.json`,
  `src/Theme.php`, `templates/`, `parts/`, **`assets/`**,
  `.github/workflows/lhci.yml`, `.lighthouserc.js`, `.wp-env.json`.
- **WP.org declined**: `.github/workflows/wporg-deploy.yml`,
  `.github/workflows/plugin-check.yml`, `readme.txt`,
  `.wordpress-org/`.
- **Deactivation example declined**: `src/Admin/`,
  `tests/Unit/Admin/`. Setup prints a WARN that lines marked
  `// OPT-IN: confirm-deactivate` must be hand-removed from
  `src/Main.php` and `tests/Unit/MainTest.php`.

### Post-`setup.sh` manual steps (before first commit)

1. Open `src/Main.php` and `tests/Unit/MainTest.php`; remove every line
   marked `// OPT-IN: confirm-deactivate`.
2. Recreate the `assets/` tree:
   - `assets/src/editor/variation.js` (stub)
   - `assets/src/frontend/lightbox.js` (stub)
   - `assets/src/frontend/gallery.css` (stub)
3. Add `@wordpress/scripts` and `photoswipe` to `package.json` dev
   deps; wire `npm run build` to wp-scripts; output to `assets/build/`.
4. Verify `.gitattributes` and `phpstan.neon.dist` don't reference
   removed paths.
5. First commit: `feat: initial apermo-gallery scaffolding`.

## Verification (after implementation, before tagging v0.1)

1. `composer install && npm install && npm run build`
2. `ddev start && ddev orchestrate`
3. Create a draft post, insert `Photo Gallery` variation, attach three
   photos with embedded EXIF.
4. Publish. View the post.
5. Inspect HTML: each item has
   `<a href="…-1600.jpg" data-apermo-exif="…">`.
6. Open one image — PhotoSwipe lightbox shows caption + EXIF line +
   filmstrip.
7. Check `wp-content/uploads/<year>/<month>/`: only the three
   whitelisted derivatives + original exist for those attachments.
8. Run automated suite: `composer test && npm run test:e2e`.
9. E2E spec opens the lightbox and asserts no axe-core violations.
