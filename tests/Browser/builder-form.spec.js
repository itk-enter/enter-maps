/**
 * @file Browser tests of the form of a map in the builder. The site has no
 * Enter, so the data set is a text field.
 */

import { expect, expectNoAxeViolations, maps, test } from "./support.js";

/**
 * The form renders again on the server after each change. Wait for it.
 *
 * @param {import("@playwright/test").Page} page The page.
 */
async function rendered(page) {
  await page.waitForLoadState("networkidle");
}

test("makes a new map with a fixed filter", async ({ page }) => {
  await page.goto("/builder");
  await page.getByRole("link", { name: "Nyt kort" }).click();

  await expect(
    page.getByRole("heading", { level: 1, name: "Nyt kort" }),
  ).toBeVisible();
  await expectNoAxeViolations(page);

  await page.getByLabel("Titel", { exact: true }).fill("Bænke i byen");
  await page.getByLabel("Søg i stederne", { exact: true }).check();
  await page.getByLabel("Navn på laget").fill("Bænke");
  await page.getByLabel("Datasæt", { exact: true }).fill("benches");
  // The path of the title is in the group "Titel" under "Titel og undertitel på
  // hvert sted".
  await page
    .getByRole("group", { name: "Titel", exact: true })
    .getByLabel("Sti", { exact: true })
    .fill("name");

  await page
    .getByRole("button", { name: "Tilføj betingelse til filteret" })
    .click();
  // The locators wait for the card, which comes with the next render.
  const condition = page.getByRole("group", { name: "Betingelse 1" });
  await condition.getByLabel("Sti", { exact: true }).fill("material");
  await condition.getByLabel("Betingelse", { exact: true }).selectOption("in");

  // "er en af" takes one value on each line.
  await expect(condition.getByLabel("Værdier")).toBeVisible();
  await expect(condition.getByLabel("Værdi", { exact: true })).toHaveCount(0);
  await condition.getByLabel("Værdier").fill("træ\nmetal");
  await rendered(page);
  await expectNoAxeViolations(page);

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();

  await expect(page).toHaveURL(/\/builder\/[0-9A-Z]{26}\/preview$/);
  await expect(page.getByRole("status")).toHaveText(
    'Kortet "Bænke i byen" er gemt.',
  );
  await expect(page.locator('main iframe[title="Bænke i byen"]')).toBeVisible();
  await expectNoAxeViolations(page);
});

test("shows the errors next to the fields", async ({ page }) => {
  await page.goto("/builder/new");

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();
  await rendered(page);

  const title = page.getByLabel("Titel", { exact: true });
  await expect(title).toHaveAttribute("aria-invalid", "true");
  await expect(title).toHaveAccessibleDescription(/Udfyld feltet/);
  await expect(page).toHaveURL(/\/builder\/new$/);
  await expectNoAxeViolations(page);
});

test("edits a map and shows the embed code", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/edit`);

  await expect(page.getByLabel("Titel", { exact: true })).toHaveValue(
    "Toiletter i Aarhus",
  );
  await expect(
    page.getByRole("heading", { level: 3, name: /Offentlige toiletter/ }),
  ).toBeVisible();
  await expectNoAxeViolations(page);

  await page.getByRole("button", { name: "Gem og vis embedkode" }).click();

  await expect(page).toHaveURL(`/builder/${maps.toilets}/embed`);
  await expect(page.locator("pre code")).toContainText(
    `/embed/leaflet/${maps.toilets}"`,
  );
});

test("previews a change without a save", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/edit`);
  await page.getByLabel("Titel", { exact: true }).fill("Udkast til toiletter");

  await page.getByRole("button", { name: "Forhåndsvis", exact: true }).click();

  const frame = page.frameLocator(
    'iframe[title="Forhåndsvisning af \\"Udkast til toiletter\\""]',
  );
  await expect(frame.locator("h1")).toHaveText("Udkast til toiletter");
  await expect(page).toHaveURL(`/builder/${maps.toilets}/edit`);
  await expectNoAxeViolations(page);

  await page.getByRole("button", { name: "Luk forhåndsvisningen" }).click();
  await expect(page.locator("iframe")).toHaveCount(0);

  // The stored map did not change.
  await page.goto("/builder");
  await expect(page.getByRole("region", { name: "Alle kort" })).toContainText(
    "Toiletter i Aarhus",
  );
});

test("opens and closes a layer, and opens it for an error", async ({
  page,
}) => {
  await page.goto(`/builder/${maps["toilets-and-parking"]}/edit`);

  const toggle = page.getByRole("button", { name: /Lag 1: Offentlige/ });
  const label = page.getByLabel("Navn på laget").first();
  await expect(toggle).toHaveAttribute("aria-expanded", "false");
  await expect(label).toBeHidden();
  await expectNoAxeViolations(page);

  await toggle.click();
  await expect(toggle).toHaveAttribute("aria-expanded", "true");
  await label.fill("");
  await toggle.click();
  await expect(label).toBeHidden();

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();

  // The error is in a closed layer, so the layer opens.
  await expect(label).toBeVisible();
  await expect(label).toHaveAttribute("aria-invalid", "true");
  await expect(page).toHaveURL(`/builder/${maps["toilets-and-parking"]}/edit`);
});
