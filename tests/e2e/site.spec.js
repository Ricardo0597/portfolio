// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('single-page profile', () => {
  test('renders the hero, the anchored sections and Person JSON-LD', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('h1.hero__name')).toBeVisible();
    for (const id of ['about', 'skills', 'experience', 'education', 'contact']) {
      await expect(page.locator(`#${id}`)).toHaveCount(1);
    }

    const types = await page.locator('script[type="application/ld+json"]').evaluateAll(
      (scripts) => scripts.map((s) => JSON.parse(s.textContent || '{}')['@type']),
    );
    expect(types).toContain('Person');
    expect(types).toContain('WebSite');
  });

  test('theme switch flips to light mode and remembers it', async ({ page }) => {
    await page.goto('/');
    const html = page.locator('html');
    const toggle = page.locator('.theme-toggle').first();
    await expect(html).not.toHaveAttribute('data-theme', 'light');

    // On phones the switch lives in the collapsed navigation drawer.
    if (!(await toggle.isVisible())) {
      await page.locator('[aria-controls][aria-expanded="false"]').first().click();
    }
    await toggle.click();
    await expect(html).toHaveAttribute('data-theme', 'light');

    await page.reload();
    await expect(html).toHaveAttribute('data-theme', 'light');
  });

  test('header and footer line up with the content column', async ({ page }) => {
    await page.goto('/');
    const edges = await page.evaluate(() => {
      // Content box (inside padding) of an element.
      const box = (selector) => {
        const el = document.querySelector(selector);
        const rect = el.getBoundingClientRect();
        const style = getComputedStyle(el);
        return {
          left: Math.round(rect.left + parseFloat(style.paddingLeft)),
          right: Math.round(rect.right - parseFloat(style.paddingRight)),
        };
      };
      return {
        header: box('.site-header__inner'),
        content: box('.about'),
        footer: box('.site-footer__inner'),
      };
    });
    expect(edges.header).toEqual(edges.content);
    expect(edges.footer).toEqual(edges.content);
  });

  test('downloads the CV as a PDF', async ({ request }) => {
    const response = await request.get('/print/pdf/node/1');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/pdf');
  });
});

test.describe('disabled sections', () => {
  for (const path of ['/projects', '/about']) {
    test(`${path} redirects to the front page`, async ({ page, baseURL }) => {
      await page.goto(path);
      expect(page.url()).toBe(`${baseURL}/`);
    });
  }
});

test.describe('APIs', () => {
  test('the projects REST endpoint is public and read-only', async ({ request }) => {
    const response = await request.get('/api/portfolio/projects?_format=json');
    expect(response.status()).toBe(200);
    expect(Array.isArray((await response.json()).data)).toBe(true);
  });

  test('node REST resource rejects writes', async ({ request }) => {
    const response = await request.delete('/node/1?_format=json');
    expect(response.status()).toBe(405);
  });
});
