/**
 * @file Waits until the site answers, and logs in as an employee. The first
 * request after a change also builds the Symfony cache, so it can be slow.
 *
 * The login goes through the mock OpenID Connect provider of
 * docker-compose.oidc.yml. The tests start with the cookies of this login,
 * from STORAGE_STATE.
 */

import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";

const ATTEMPTS = 30;

/** The cookies of the login, which playwright.config.js gives to each test. */
export const STORAGE_STATE = fileURLToPath(
  new URL("../../var/browser/state.json", import.meta.url),
);

/**
 * @param {import("@playwright/test").FullConfig} config
 */
export default async function globalSetup(config) {
  const { baseURL } = config.projects[0].use;

  await waitForSite(baseURL);
  await logIn(baseURL);
}

/**
 * @param {string} baseURL The address of the site.
 */
async function waitForSite(baseURL) {
  for (let attempt = 1; attempt <= ATTEMPTS; attempt++) {
    try {
      await fetch(baseURL);

      return;
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 1000));
    }
  }

  throw new Error(`The site at ${baseURL} does not answer.`);
}

/**
 * Logs in as the user "employee" of the mock provider, and saves the cookies.
 *
 * @param {string} baseURL The address of the site.
 */
async function logIn(baseURL) {
  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL });
  // The login page of the provider loads its CSS from a CDN. The tests do not need it.
  await page.route("https://cdn.jsdelivr.net/**", (route) =>
    route.fulfill({ status: 204 }),
  );

  await page.goto("/builder");
  await page.getByRole("button", { name: "Authorize as employee" }).click();
  await page.waitForURL(`${baseURL}/builder`);

  await page.context().storageState({ path: STORAGE_STATE });
  await browser.close();
}
