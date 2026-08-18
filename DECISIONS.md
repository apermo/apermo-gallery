# Apermo Gallery — Decision Log

Records design decisions made before code was written. Future changes
that reverse one of these should update both this file and
[`PLAN.md`](PLAN.md).

## 2026-05-17 · Image-size strategy

**Decision**: Cleanup-after-the-fact. Let WP generate normally; delete
non-whitelisted derivatives on flag-set.

**Alternatives considered**:

- Skip-at-generation via `intermediate_image_sizes_advanced` only.
  Rejected: requires the flag to be set *before* upload, which doesn't
  match the realistic editor workflow (drag photos into a gallery
  block — by then sizes already exist).
- Hybrid (skip when pre-flagged, cleanup otherwise). Rejected for v0.1:
  two code paths to maintain; cleanup alone is sufficient.

**Trade-off accepted**: a brief window where extra files sit on disk
between upload and flag-set. Cleanup is idempotent.

## 2026-05-17 · Editor opt-in

**Decision**: Block variation `Photo Gallery` applying className
`is-apermo-gallery`.

**Alternatives considered**:

- Pure block style (zero editor JS). Rejected on UX grounds — a named
  inserter entry is clearer for the apermo.de authoring flow than
  hunting in the Styles sidebar.
- Both style + variation. Rejected: doubles the test surface for
  marginal UX gain.

**Trade-off accepted**: small editor JS bundle (paid once via
`@wordpress/scripts`).

## 2026-05-17 · Lightbox + EXIF delivery

**Decision**: PhotoSwipe v5 with EXIF inline as `data-apermo-exif`.

**Alternatives considered**:

- Native `core/image` lightbox (WP 6.4+). Rejected: no EXIF, no
  filmstrip.
- Custom mini-lightbox. Rejected: reinventing.
- REST endpoint hit on lightbox-open. Rejected for v0.1: extra
  round-trip, public REST surface to lock down. Reconsider if galleries
  routinely exceed 50 images.
- Inline JSON blob per gallery. Rejected for v0.1: parsing step adds
  complexity without meaningful payload savings at expected sizes.

**Trade-off accepted**: HTML bloat scales with gallery size (~200–400
bytes per image pre-gzip).

## 2026-05-17 · EXIF caption scope

**Decision**: Caption line + camera body + exposure line. GPS, lens,
copyright deferred.

**Alternatives considered**:

- Exposure-only line (no body). Rejected: body is the most Flickr-like
  line and is already in `image_meta` for free.
- Hover/toggle reveal. Rejected: extra UI state for no real benefit.

## 2026-05-17 · Image sizes

**Decision**: Three sizes — 400 (sq, soft-cropped) / 1000 / 1600 +
original.

**Alternatives considered**:

- Two sizes (400 / 1600). Rejected: srcset has only two stops,
  mid-range viewports overshoot to 1600 and pay ~3× bandwidth. The
  seed's stated goal was "at most two derivatives", but the bandwidth
  math beat the byte count.
- Two sizes + on-the-fly resize endpoint. Rejected for v0.1 scope.

## 2026-05-17 · PhotoSwipe distribution

**Decision**: npm + `@wordpress/scripts` build. Source under
`assets/src/`, output under `assets/build/`. Output committed.

**Alternatives considered**:

- Vendored static files. Rejected: needs editor JS for the variation
  anyway, so a build pipeline is paid regardless. Single pipeline beats
  two distribution strategies.
- npm + import maps. Rejected: niche, gains nothing here.

**Why source isn't under `src/`**: `src/` is PSR-4 PHP per the
template.

## 2026-05-17 · Manual flag UX

**Decision**: Media Library list-view bulk action only
(`Mark as gallery image`).

**Alternatives considered**:

- Bulk action + media-modal checkbox. Rejected for v0.1: extra JS for
  the media frame + edit-attachment field for marginal UX gain when
  auto-flag on save already covers the common path.
- WP-CLI only. Rejected: assumes terminal access; bulk action is more
  accessible.

## 2026-05-17 · Naming

**Decision**: `apermo-gallery` / `Apermo\Gallery` / `_apermo_gallery_image`
/ `is-apermo-gallery` / `data-apermo-exif` everywhere.

**Alternatives considered**: short prefix (`apg-`) for runtime.
Rejected: two conventions to grep across for ~30% pre-gzip byte savings
on a handful of attributes.
