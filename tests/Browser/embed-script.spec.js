/**
 * @file Browser tests of the embed script on a page of another site. The
 * page is a stub at http://localhost:9999, so the frame of the map comes from
 * another origin, as on a real site.
 *
 * Chromium blocks a request from another site to a private address, and the
 * dev site has a private address in Docker. The tests turn off that check.
 * On a real site, both addresses are public, so the check does not apply.
 */

import { expect, expectNoAxeViolations, maps, test } from "./support.js";

const HOST = "http://localhost:9999/";
// The map without a zoom gate, so the list shows at the start.
const MAP = "toilets-and-parking";

test.use({
  launchOptions: {
    args: [
      "--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessChecks,BlockInsecurePrivateNetworkRequests",
    ],
  },
});

/**
 * Opens a page of another site with the embed code of one or more maps, and
 * a link before and after the maps.
 *
 * @param {import("@playwright/test").Page} page The page.
 * @param {string} baseURL The address of the site of the maps.
 * @param {object} [options] The options.
 * @param {string[]} [options.ids] The ids of the maps.
 * @param {boolean} [options.blockScript] Blocks the embed script, as a strict page does.
 */
async function openHost(
  page,
  baseURL,
  { ids = [maps[MAP]], blockScript = false } = {},
) {
  const codes = ids.map(
    (
      id,
    ) => `<div data-enter-map="${id}"><a href="${baseURL}/embed/leaflet/${id}">Kort ${id}</a></div>
      <script src="${baseURL}/embed/v1.js" async></script>`,
  );
  await page.route(HOST, (route) =>
    route.fulfill({
      contentType: "text/html; charset=utf-8",
      body: `<!DOCTYPE html>
        <html lang="da">
          <head><meta charset="utf-8"><title>En anden side</title></head>
          <body>
            <main>
              <h1>En anden side</h1>
              <a href="#before">Før kortet</a>
              ${codes.join("\n")}
              <a href="#after">Efter kortet</a>
            </main>
          </body>
        </html>`,
    }),
  );
  if (blockScript) {
    await page.route("**/embed/v1.js", (route) => route.abort());
  }
  await page.goto(HOST);
}

/**
 * @param {import("@playwright/test").Locator} frame The iframe.
 * @returns {Promise<number>} The height of the iframe on the page.
 */
const frameHeight = (frame) =>
  frame.evaluate((element) =>
    Math.round(element.getBoundingClientRect().height),
  );

/**
 * @param {import("@playwright/test").Locator} frame The iframe.
 * @returns {Promise<number>} The height of the map inside the iframe.
 */
const mapHeight = (frame) =>
  frame
    .contentFrame()
    .locator("main")
    .evaluate((element) => Math.ceil(element.getBoundingClientRect().height));

test.describe("the embed script", () => {
  test("replaces the div with the frame of the map", async ({
    page,
    baseURL,
  }) => {
    await openHost(page, baseURL);

    const frame = page.locator("[data-enter-map] > iframe");
    await expect(frame).toHaveAttribute("title", `Kort ${maps[MAP]}`);
    await expect(frame).toHaveAttribute(
      "src",
      `${baseURL}/embed/leaflet/${maps[MAP]}?autoheight=1`,
    );
    await expect(
      frame.contentFrame().getByRole("heading", { name: "Resultater" }),
    ).toBeVisible();
    await expectNoAxeViolations(page);
  });

  test("gives the frame the height of the map on a phone", async ({
    page,
    baseURL,
  }) => {
    await page.setViewportSize({ width: 390, height: 2000 });
    await openHost(page, baseURL);
    const frame = page.locator("[data-enter-map] > iframe");
    const map = frame.contentFrame();
    await expect(
      map.getByRole("heading", { name: "Resultater" }),
    ).toBeVisible();

    await expect
      .poll(async () => (await frameHeight(frame)) - (await mapHeight(frame)))
      .toBe(0);
    const before = await frameHeight(frame);

    // A filter with fewer places makes the list, and thus the map, shorter.
    await map.getByRole("checkbox", { name: "Kun gratis" }).check();
    await expect.poll(() => frameHeight(frame)).toBeLessThan(before);
    await expect
      .poll(async () => (await frameHeight(frame)) - (await mapHeight(frame)))
      .toBe(0);
  });

  test("makes the frame no higher than the window", async ({
    page,
    baseURL,
  }) => {
    await page.setViewportSize({ width: 390, height: 600 });
    await openHost(page, baseURL);
    const frame = page.locator("[data-enter-map] > iframe");
    await frame.scrollIntoViewIfNeeded();

    await expect.poll(() => frameHeight(frame)).toBe(600);
    expect(await mapHeight(frame)).toBeGreaterThan(600);
  });

  test("keeps the fixed height when the sidebar is next to the map", async ({
    page,
    baseURL,
  }) => {
    await page.setViewportSize({ width: 1280, height: 1000 });
    await openHost(page, baseURL);
    const frame = page.locator("[data-enter-map] > iframe");
    await expect(
      frame.contentFrame().getByRole("heading", { name: "Resultater" }),
    ).toBeVisible();

    await expect.poll(() => frameHeight(frame)).toBe(700);
  });

  test("ignores a height from another origin", async ({ page, baseURL }) => {
    await page.setViewportSize({ width: 390, height: 2000 });
    await openHost(page, baseURL);
    const frame = page.locator("[data-enter-map] > iframe");
    await expect
      .poll(async () => (await frameHeight(frame)) - (await mapHeight(frame)))
      .toBe(0);
    const height = await frameHeight(frame);

    await page.evaluate(() =>
      window.postMessage(
        { type: "enter-map:height", height: 50, wide: false },
        "*",
      ),
    );

    await page.waitForTimeout(200);
    expect(await frameHeight(frame)).toBe(height);
  });

  test("gives each map on the page its own frame and height", async ({
    page,
    baseURL,
  }) => {
    await page.setViewportSize({ width: 390, height: 3000 });
    await openHost(page, baseURL, {
      ids: [maps[MAP], maps["toilets-and-parking"]],
    });

    const frames = page.locator("[data-enter-map] > iframe");
    await expect(frames).toHaveCount(2);
    for (const frame of await frames.all()) {
      await frame.scrollIntoViewIfNeeded();
      await expect
        .poll(async () => (await frameHeight(frame)) - (await mapHeight(frame)))
        .toBe(0);
    }
  });

  test("puts the map in the tab order of the page", async ({
    page,
    baseURL,
  }) => {
    await openHost(page, baseURL);
    const frame = page.locator("[data-enter-map] > iframe");
    await expect(
      frame.contentFrame().getByRole("heading", { name: "Resultater" }),
    ).toBeVisible();

    // Tab from the link before the map goes to the skip link of the map.
    await page.getByRole("link", { name: "Før kortet" }).focus();
    await page.keyboard.press("Tab");
    await expect(frame).toBeFocused();
    await expect(
      frame
        .contentFrame()
        .getByRole("link", { name: "Spring til liste og kort" }),
    ).toBeFocused();

    // Shift+Tab from the link after the map goes back into the map.
    await page.getByRole("link", { name: "Efter kortet" }).focus();
    await page.keyboard.press("Shift+Tab");
    await expect(frame).toBeFocused();
  });

  test("shows the link to the map if the page blocks the script", async ({
    page,
    baseURL,
  }) => {
    await openHost(page, baseURL, { blockScript: true });

    const link = page.getByRole("link", { name: `Kort ${maps[MAP]}` });
    await expect(link).toHaveAttribute(
      "href",
      `${baseURL}/embed/leaflet/${maps[MAP]}`,
    );
    await expect(page.locator("iframe")).toHaveCount(0);
  });
});
