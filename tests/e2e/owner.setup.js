// @ts-check
const { execSync } = require('node:child_process');
const { test: setup, expect } = require('@playwright/test');

const { OWNER_STATE } = require('./paths');

/**
 * Logs in as the site owner (uid 1) once and saves the session.
 *
 * One-time login links stop working as soon as the user logs in again, so
 * parallel tests must share one session instead of each fetching a link.
 * Uses E2E_LOGIN_URL when set, otherwise asks DDEV for a link.
 */
setup('log in as the site owner', async ({ page, baseURL }) => {
  const url = process.env.E2E_LOGIN_URL
    || execSync(`ddev drush user:login --no-browser --uri=${baseURL}`, { encoding: 'utf8' }).trim();
  await page.goto(url);
  await expect(page).not.toHaveURL(/\/user\/reset\//);
  await page.context().storageState({ path: OWNER_STATE });
});
