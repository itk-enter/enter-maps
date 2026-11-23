/**
 * @file Browser tests of the list of maps in the builder. The delete test
 * restores the map at the end, so the other tests can still use it.
 */

import { expect, expectNoAxeViolations, maps, test } from "./support.js";

test("lists the maps", async ({ page }) => {
  await page.goto("/builder");

  await expect(
    page.getByRole("heading", { level: 1, name: "Kort" }),
  ).toBeVisible();
  const list = page.getByRole("region", { name: "Alle kort" });
  await expect(list).toContainText("Toiletter i Aarhus");
  await expect(list).toContainText("Tilgængelighed i midtbyen");
  await expectNoAxeViolations(page);
});

test("scrolls the table with the keyboard on a phone", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 800 });
  await page.goto("/builder");

  const region = page.getByRole("region", { name: "Alle kort" });
  const scroll = () => region.evaluate((element) => element.scrollLeft);
  expect(
    await region.evaluate(
      (element) => element.scrollWidth > element.clientWidth,
    ),
    "The table is wider than the screen",
  ).toBe(true);
  await expectNoAxeViolations(page);

  await region.focus();
  await page.keyboard.press("ArrowRight");

  await expect.poll(scroll).toBeGreaterThan(0);
});

test("shows and copies the embed code", async ({ page }) => {
  await page.goto("/builder");

  await page
    .getByRole("link", { name: 'Embedkode til kortet "Toiletter i Aarhus"' })
    .click();

  await expect(
    page.getByRole("heading", {
      level: 1,
      name: 'Embedkode til "Toiletter i Aarhus"',
    }),
  ).toBeVisible();
  const script = page.getByRole("region", { name: "Kode med script" });
  const iframe = page.getByRole("region", { name: "Kun en iframe" });
  await expect(script.locator("code")).toContainText(
    `<div data-enter-map="${maps.toilets}">`,
  );
  await expect(script.locator("code")).toContainText("/embed/v1.js");
  await expect(iframe.locator("code")).toContainText(
    `/embed/leaflet/${maps.toilets}"`,
  );
  await expect(iframe.locator("code")).toContainText(
    'title="Toiletter i Aarhus"',
  );
  await expectNoAxeViolations(page);

  await iframe.getByRole("button", { name: "Kopiér iframe-koden" }).click();

  // The test site uses http, so the controller selects the code and uses the
  // old copy command. The real site uses https and the clipboard API.
  await expect(iframe.getByRole("status")).toHaveText("Koden er kopieret.");
  expect(await page.evaluate(() => window.getSelection().toString())).toBe(
    await iframe.locator("code").textContent(),
  );
});

test("changes the height of the embed code", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/embed`);
  // Only the iframe code has a height field.
  const code = page
    .getByRole("region", { name: "Kun en iframe" })
    .locator("code");
  await expect(code).toContainText('height="700"');

  await page.getByLabel("Højde i pixels").fill("900");
  await page.getByRole("button", { name: "Opdater koden" }).click();

  await expect(code).toContainText('height="900"');

  await page.getByLabel("Højde i pixels").fill("50");
  await page.getByRole("button", { name: "Opdater koden" }).click();

  await expect(page.getByText("Værdien skal være mellem 300 og 3000.")).toBeVisible();
  await expect(code).toContainText('height="700"');
  await expectNoAxeViolations(page);
});

test("deletes and restores a map", async ({ page }) => {
  const title = "Tilgængelighed i midtbyen";

  await page.goto("/builder");
  await page.getByRole("link", { name: `Slet kortet "${title}"` }).click();

  await expect(
    page.getByRole("heading", { level: 1, name: `Slet kortet "${title}"?` }),
  ).toBeVisible();
  await expectNoAxeViolations(page);

  await page.getByRole("button", { name: "Slet kortet" }).click();

  await expect(page.getByRole("status")).toHaveText(
    `Kortet "${title}" er slettet.`,
  );
  const deleted = page.getByRole("region", { name: "Slettede kort" });
  await expect(deleted).toContainText(title);
  await expectNoAxeViolations(page);

  await deleted
    .getByRole("button", { name: `Gendan kortet "${title}"` })
    .click();

  await expect(page.getByRole("status")).toHaveText(
    `Kortet "${title}" er gendannet.`,
  );
  await expect(page.getByRole("region", { name: "Alle kort" })).toContainText(
    title,
  );
});

test("links to the builder from the front page", async ({ page }) => {
  await page.goto("/");

  await expect(
    page.getByRole("heading", { level: 2, name: "Kortbygger" }),
  ).toBeVisible();
  await expectNoAxeViolations(page);

  await page.getByRole("link", { name: "Åbn kortbyggeren" }).click();

  await expect(page).toHaveURL("/builder");
});
