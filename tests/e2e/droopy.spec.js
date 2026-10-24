// @ts-check
const { test, expect } = require('@playwright/test');

const ASK = '**/api/portfolio/ask';

// Droopy floats and blinks; reduced motion (which the component honours)
// keeps the launcher still enough to click reliably.
test.use({ contextOptions: { reducedMotion: 'reduce' } });

/**
 * Opens the Droopy panel and returns its main locators.
 */
async function openDroopy(page) {
  await page.goto('/');
  const launcher = page.locator('.droopy__launcher');
  await launcher.click();
  const panel = page.locator('#droopy-panel');
  await expect(panel).toBeVisible();
  await expect(launcher).toHaveAttribute('aria-expanded', 'true');
  return { launcher, panel, log: panel.locator('.droopy__log') };
}

test('opens with suggested questions and closes again', async ({ page }) => {
  const { launcher, panel } = await openDroopy(page);
  await expect(panel.locator('.droopy__chip')).not.toHaveCount(0);

  await panel.locator('.droopy__close').click();
  await expect(panel).toBeHidden();
  await expect(launcher).toHaveAttribute('aria-expanded', 'false');
});

test('renders answers as text and only whitelisted actions', async ({ page }) => {
  await page.route(ASK, (route) => route.fulfill({
    json: {
      answer: '<img src=x onerror="window.pwned=1"> Ricardo builds Drupal sites.',
      actions: [
        { type: 'scroll', target: 'experience' },
        { type: 'email' },
        { type: 'scroll', target: 'not-a-section' },
      ],
      provider: 'groq',
    },
  }));
  const { panel, log } = await openDroopy(page);
  await panel.locator('.droopy__chip:not([data-mode])').first().click();

  const answer = log.locator('.droopy__msg--bot').last();
  await expect(answer).toContainText('<img src=x');
  await expect(log.locator('img')).toHaveCount(0);
  expect(await page.evaluate(() => window.pwned)).toBeUndefined();

  const actions = log.locator('.droopy__action');
  await expect(actions).toHaveText(['See experience', 'Email Ricardo']);

  // Clearing the conversation brings the suggestions back.
  await panel.locator('.droopy__clear').click();
  await expect(log.locator('.droopy__msg--user')).toHaveCount(0);
  await expect(panel.locator('.droopy__chips')).toBeVisible();
});

test('falls back to the napping answer when the endpoint fails', async ({ page }) => {
  await page.route(ASK, (route) => route.abort());
  const { panel, log } = await openDroopy(page);
  await panel.locator('.droopy__input').fill('Is he available?');
  await panel.locator('.droopy__send').click();

  await expect(log.locator('.droopy__msg--bot').last()).toContainText('napping');
  await expect(log.locator('.droopy__action')).toHaveText(['Email Ricardo']);
  // The question stays in the box so the visitor can retry.
  await expect(panel.locator('.droopy__input')).toHaveValue('Is he available?');
});

test('the ask endpoint rejects foreign origins before calling a provider', async ({ request }) => {
  const response = await request.post('/api/portfolio/ask', {
    headers: { Origin: 'https://evil.example' },
    data: { question: 'Hi', mode: 'chat', history: [] },
  });
  expect(response.status()).toBe(403);
});
