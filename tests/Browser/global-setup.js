/**
 * @file Waits until the site answers. The first request after a change also
 * builds the Symfony cache, so it can be slow.
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
