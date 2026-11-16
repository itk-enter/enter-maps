/**
 * @file Waits until the site of the browser tests answers. The task starts
 * the PHP web server just before the tests.
 */

const ATTEMPTS = 30;

/**
 * @param {import("@playwright/test").FullConfig} config
 */
export default async function globalSetup(config) {
  const { baseURL } = config.projects[0].use;

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
