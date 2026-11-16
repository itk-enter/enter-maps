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
  const code = page.locator("pre code");
  await expect(code).toContainText(`/embed/leaflet/${maps.toilets}"`);
  await expect(code).toContainText('title="Toiletter i Aarhus"');
  await expectNoAxeViolations(page);

  await page.getByRole("button", { name: "Kopiér koden" }).click();

  // The test site uses http, so the controller selects the code and uses the
  // old copy command. The real site uses https and the clipboard API.
  await expect(page.getByRole("status")).toHaveText("Koden er kopieret.");
  expect(await page.evaluate(() => window.getSelection().toString())).toBe(
    await code.textContent(),
  );
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
