// @ts-check
const { test, expect } = require('@playwright/test');

const { OWNER_STATE } = require('./paths');

const TOOL = '/admin/portfolio/tailored-cv';

// The mascot floats; reduced motion keeps it clickable (see droopy.spec.js).
test.use({ contextOptions: { reducedMotion: 'reduce' } });

test('anonymous visitors cannot reach the tool or see its link', async ({ page, request }) => {
  const response = await request.get(TOOL);
  expect(response.status()).toBe(403);

  await page.goto('/');
  await expect(page.locator('.droopy')).not.toHaveAttribute('data-tailor-url');
});

test.describe('as the site owner', () => {
  // Session saved by owner.setup.js.
  test.use({ storageState: OWNER_STATE });

  test('opens the tool with the job description step', async ({ page }) => {
    await page.goto(TOOL);
    await expect(page.getByLabel('Job description')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Generate draft' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Download PDF' })).toHaveCount(0);
  });

  test('typing /tailored-cv in Droopy links to the tool without calling the API', async ({ page }) => {
    let asked = false;
    await page.route('**/api/portfolio/ask', (route) => {
      asked = true;
      return route.abort();
    });
    await page.goto('/');
    await page.locator('.droopy__launcher').click();
    await page.locator('.droopy__input').fill('/tailored-cv');
    await page.locator('.droopy__send').click();

    const link = page.locator('.droopy__log a', { hasText: 'Open tailored-CV tool' });
    await expect(link).toHaveAttribute('href', TOOL);
    expect(asked).toBe(false);
  });
});
