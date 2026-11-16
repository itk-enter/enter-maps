/**
 * @file Browser tests of the list of maps in the builder. The tests import
 * their own map, so that they do not change the fixture maps of the other
 * tests.
 */

import { readFileSync } from "node:fs";
import { expect, expectNoAxeViolations, test } from "./support.js";

const fixture = readFileSync(
  new URL("../Fixtures/map-config/toilets.json", import.meta.url),
);

/**
 * @param {import("@playwright/test").Page} page The page.
 * @param {string} name The file name.
 * @param {Buffer|string} content The content of the file.
 */
async function importFile(page, name, content) {
  await page.getByLabel("JSON-fil med kortets konfiguration").setInputFiles({
    name,
    mimeType: "application/json",
    buffer: Buffer.from(content),
  });
  await page.getByRole("button", { name: "Importér" }).click();
}

test("lists the maps", async ({ page }) => {
  await page.goto("/builder");

  await expect(
    page.getByRole("heading", { level: 1, name: "Kort" }),
  ).toBeVisible();
  const maps = page.getByRole("region", { name: "Alle kort" });
  await expect(maps).toContainText("Toiletter i Aarhus");
  await expect(maps).toContainText("Tilgængelighed i midtbyen");
  await expectNoAxeViolations(page);
});

test("tells why a file is not a valid map", async ({ page }) => {
  await page.goto("/builder");

  await importFile(page, "map.json", '{"version": 1}');

  const field = page.getByLabel("JSON-fil med kortets konfiguration");
  await expect(field).toHaveAttribute("aria-invalid", "true");
  await expect(field).toHaveAccessibleDescription(
    /Filen er ikke en gyldig kortkonfiguration/,
  );
  await expectNoAxeViolations(page);
});

test("imports, deletes and restores a map", async ({ page }) => {
  const title = "Toiletter til sletning";
  const config = { ...JSON.parse(fixture), title };

  await page.goto("/builder");
  await importFile(page, "map.json", JSON.stringify(config));

  await expect(page.getByRole("status")).toHaveText(
    `Kortet "${title}" er importeret.`,
  );

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
