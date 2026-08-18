const { test, expect } = require('@playwright/test');
const { expectNoA11yViolations } = require('./helpers/a11y');

/**
 * Gallery E2E coverage.
 *
 * Skipped by default: needs a published post containing a Photo Gallery
 * variation. To run locally, publish such a post and export its slug via
 * APERMO_GALLERY_TEST_SLUG=hello-gallery npx playwright test gallery.spec.
 */
const GALLERY_SLUG = process.env.APERMO_GALLERY_TEST_SLUG;

test.describe('Photo Gallery — rendered markup', () => {
    test.skip(!GALLERY_SLUG, 'Set APERMO_GALLERY_TEST_SLUG to enable.');

    test('emits anchors with data-apermo-exif and the lightbox class', async ({ page }) => {
        await page.goto(`/${GALLERY_SLUG}/`);

        const gallery = page.locator('figure.wp-block-gallery.apermo-gallery').first();
        await expect(gallery).toBeVisible();

        const anchors = gallery.locator('a[data-apermo-exif]');
        await expect(anchors.first()).toBeVisible();

        const count = await anchors.count();
        expect(count).toBeGreaterThan(0);

        const firstAnchor = anchors.first();
        await expect(firstAnchor).toHaveAttribute('data-pswp-width', /^\d+$/);
        await expect(firstAnchor).toHaveAttribute('data-pswp-height', /^\d+$/);

        const srcset = await firstAnchor.locator('img').getAttribute('srcset');
        expect(srcset).toMatch(/apermo-gallery-(thumb|medium|large)/);
    });

    test('opens PhotoSwipe with the EXIF caption', async ({ page }) => {
        await page.goto(`/${GALLERY_SLUG}/`);

        await page.locator('figure.apermo-gallery a[data-apermo-exif]').first().click();

        const pswp = page.locator('.pswp--open');
        await expect(pswp).toBeVisible();

        const caption = page.locator('.pswp__apermo-caption .apermo-gallery-exif');
        await expect(caption).toBeVisible();
        await expect(caption).not.toBeEmpty();
    });

    test('rendered gallery has no axe-core violations', async ({ page }) => {
        await page.goto(`/${GALLERY_SLUG}/`);
        await expectNoA11yViolations(page, { include: 'figure.apermo-gallery' });
    });
});
