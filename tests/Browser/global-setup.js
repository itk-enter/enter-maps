/**
 * @file Waits until the site answers, and logs in once. The first request
 * after a change also builds the Symfony cache, so it can be slow.
 *
 * The first login of "employee" makes the user in the empty database. The
 * workers log in at the same time later, see support.js, and must not make
 * the same user twice.
 */

import { chromium } from "@playwright/test";
import { logIn, stateFile } from "./login.js";

const ATTEMPTS = 30;

/**
 * @param {import("@playwright/test").FullConfig} config
 */
export default async function globalSetup(config) {
  const { baseURL } = config.projects[0].use;

  await waitForSite(baseURL);

  const browser = await chromium.launch();
  await logIn(browser, baseURL, stateFile("state-setup.json"));
  await browser.close();
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
