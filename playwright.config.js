// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * End-to-end smoke tests. They run against the local DDEV site, which must be
 * up (`ddev start`) with content in place; nothing here installs Drupal.
 *
 * Calls to the LLM endpoint are mocked in the browser, so the suite never
 * spends provider quota or depends on model output.
 */
module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  fullyParallel: true,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'https://portfolio.ddev.site',
    // DDEV's local certificate is not trusted inside every browser sandbox.
    ignoreHTTPSErrors: true,
    colorScheme: 'dark',
    trace: 'retain-on-failure',
  },
  projects: [
    // Logs in once as the owner for the tests that need a session.
    { name: 'setup', testMatch: /owner\.setup\.js/ },
    { name: 'desktop', use: { ...devices['Desktop Chrome'] }, dependencies: ['setup'] },
    { name: 'phone', use: { ...devices['Pixel 7'] }, dependencies: ['setup'] },
  ],
});
