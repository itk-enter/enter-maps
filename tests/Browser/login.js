/**
 * @file The login of the browser tests, at the mock OpenID Connect provider
 * of docker-compose.oidc.yml. global-setup.js and support.js use it.
 */

/**
 * Logs in as the user "employee" of the mock provider, and saves the cookies.
 *
 * @param {import("@playwright/test").Browser} browser The browser.
 * @param {string} baseURL The address of the site.
 * @param {string} file The file for the cookies.
 */
export async function logIn(browser, baseURL, file) {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  // The login page of the provider loads its CSS from a CDN. The tests do not need it.
  await page.route("https://cdn.jsdelivr.net/**", (route) =>
    route.fulfill({ status: 204 }),
  );

  await page.goto("/builder");
  await page.getByRole("button", { name: "Authorize as employee" }).click();
  await page.waitForURL(`${baseURL}/builder`);

  await context.storageState({ path: file });
  await context.close();
}

/**
 * @param {string} name The name of the file in var/browser/.
 * @returns {string} The path of the file for the cookies of a login.
 */
export function stateFile(name) {
  return new URL(`../../var/browser/${name}`, import.meta.url).pathname;
}
