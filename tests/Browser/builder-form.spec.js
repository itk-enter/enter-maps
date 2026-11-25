/**
 * @file Browser tests of the form of a map in the builder. The site has no
 * Enter, so the data set and the paths are text fields, and the data set
 * step has no check boxes.
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

/**
 * Shows a step of the form with the menu of the steps.
 *
 * @param {import("@playwright/test").Page} page The page.
 * @param {string} name The name of the step, for example "Lag".
 */
async function showStep(page, name) {
  const button = page
    .getByRole("navigation", { name: "Trin i formularen" })
    .getByRole("button", { name, exact: true });
  // A click before the Live Component starts does nothing, so click again.
  await expect(async () => {
    await button.click();
    await expect(button).toHaveAttribute("aria-current", "step", {
      timeout: 1000,
    });
  }).toPass();
}

test("makes a new map with a fixed filter", async ({ page }) => {
  await page.goto("/builder");
  await page.getByRole("link", { name: "Nyt kort" }).click();

  await expect(
    page.getByRole("heading", { level: 1, name: "Nyt kort" }),
  ).toBeVisible();
  await expectNoAxeViolations(page);
  // Before a title, the layers and the view are disabled. A new map has no embed code.
  const steps = page.getByRole("navigation", { name: "Trin i formularen" });
  await expect(steps.getByRole("button", { name: "Lag" })).toBeDisabled();
  await expect(page.getByRole("link", { name: "Embedkode" })).toHaveCount(0);

  await page.getByLabel("Titel", { exact: true }).fill("Bænke i byen");
  await page.getByLabel("Søg i stederne", { exact: true }).check();
  await showStep(page, "Datasæt");
  await expect(
    page.getByText("Enter svarer ikke, så listen over datasæt mangler."),
  ).toBeVisible();
  // Without Enter, the title is sufficient to open the layers.
  await showStep(page, "Lag");
  await page.getByRole("button", { name: "Tilføj et lag til kortet" }).click();
  await page.getByLabel("Navn på laget").fill("Bænke");
  // The section of the data set step also has the name "Datasæt".
  await page
    .locator("#step-layers")
    .getByLabel("Datasæt", { exact: true })
    .fill("benches");
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
  // The user of the map can turn the condition on and off.
  await condition.getByLabel("Brugeren kan slå betingelsen til og fra").check();
  await condition.getByLabel("Tekst i sidepanelet").fill("Træ eller metal");
  await rendered(page);
  await expectNoAxeViolations(page);

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();

  // The form asks first, over the map from the form. Escape goes back to the form.
  const question = page.getByRole("dialog", { name: "Opret nyt kort?" });
  await expect(question).toBeVisible();
  await expect(
    question.getByRole("button", { name: "Tilbage til formularen" }),
  ).toBeFocused();
  // The user can use the map before the save. The bar is above the map, not on it.
  const frame = question.locator("iframe");
  const filter = frame
    .contentFrame()
    .getByRole("checkbox", { name: "Træ eller metal" });
  await filter.check();
  await expect(filter).toBeChecked();
  const bar = await question
    .getByRole("heading")
    .evaluate(
      (heading) =>
        heading.parentElement.parentElement.getBoundingClientRect().bottom,
    );
  expect((await frame.boundingBox()).y).toBeGreaterThanOrEqual(bar);
  await expectNoAxeViolations(page);
  // Escape in the map belongs to the map, for example to close a place. In the bar, it goes back to the form.
  await question
    .getByRole("button", { name: "Tilbage til formularen" })
    .focus();
  await page.keyboard.press("Escape");
  await expect(question).toHaveCount(0);
  await expect(page).toHaveURL("/builder/new");

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();
  await page.getByRole("button", { name: "Opret kortet" }).click();

  await expect(page).toHaveURL(/\/builder\/[0-9A-Z]{26}\/preview$/);
  await expect(page.getByRole("status")).toHaveText(
    'Kortet "Bænke i byen" er gemt.',
  );
  await expect(
    page
      .frameLocator('main iframe[title="Bænke i byen"]')
      .getByRole("checkbox", { name: "Træ eller metal" }),
  ).not.toBeChecked();
  await expect(page.locator('main iframe[title="Bænke i byen"]')).toBeVisible();
  await expectNoAxeViolations(page);
});

test("shows the errors next to the fields", async ({ page }) => {
  await page.goto("/builder/new");
  await showStep(page, "Datasæt");

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();
  await rendered(page);

  // The form shows the first step with an error, and the menu marks it. The
  // error of the map is above the steps.
  await expect(
    page.getByRole("button", { name: "Indstillinger (har fejl)" }),
  ).toHaveAttribute("aria-current", "step");
  await expect(page.getByText("Kortet skal have mindst ét lag.")).toBeVisible();
  const title = page.getByLabel("Titel", { exact: true });
  await expect(title).toHaveAttribute("aria-invalid", "true");
  await expect(title).toHaveAccessibleDescription(/Udfyld feltet/);
  await expect(page).toHaveURL(/\/builder\/new$/);
  await expectNoAxeViolations(page);
});

test("warns before it saves a change to a stored map", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/edit`);
  await page.getByLabel("Titel", { exact: true }).fill("Nye toiletter");
  await rendered(page);

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();

  const question = page.getByRole("dialog", { name: "Gem ændringerne?" });
  await expect(question).toContainText(
    "Ændringerne vises med det samme på alle sider, som viser kortet.",
  );
  await expect(
    question.getByRole("button", { name: "Gem ændringerne" }),
  ).toBeVisible();
  await expectNoAxeViolations(page);

  // The test goes back without a save, so the other tests still have the map.
  await question
    .getByRole("button", { name: "Tilbage til formularen" })
    .click();
  await expect(question).toHaveCount(0);
  await expect(page.getByLabel("Titel", { exact: true })).toHaveValue(
    "Nye toiletter",
  );
});

test("edits a map and shows the embed code", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/edit`);

  await expect(page.getByLabel("Titel", { exact: true })).toHaveValue(
    "Toiletter i Aarhus",
  );
  await showStep(page, "Lag");
  await expect(
    page.getByRole("heading", { level: 3, name: /Offentlige toiletter/ }),
  ).toBeVisible();
  await expectNoAxeViolations(page);

  await page.getByRole("link", { name: "Embedkode", exact: true }).click();

  await expect(page).toHaveURL(`/builder/${maps.toilets}/embed`);
  await expect(page.locator("pre code")).toContainText(
    `/embed/leaflet/${maps.toilets}"`,
  );
});

test("previews a change without a save", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/edit`);
  await page.getByLabel("Titel", { exact: true }).fill("Udkast til toiletter");
  await showStep(page, "Forhåndsvisning");

  await page.getByRole("button", { name: "Opdater kortet" }).click();

  const frame = page.frameLocator(
    'iframe[title="Forhåndsvisning af \\"Udkast til toiletter\\""]',
  );
  await expect(frame.locator("h1")).toHaveText("Udkast til toiletter");
  await expect(page).toHaveURL(`/builder/${maps.toilets}/edit`);
  await expectNoAxeViolations(page);

  // The stored map did not change.
  await page.goto("/builder");
  await expect(page.getByRole("region", { name: "Alle kort" })).toContainText(
    "Toiletter i Aarhus",
  );
});

test("copies the view of the map into the fields", async ({ page }) => {
  await page.goto(`/builder/${maps.toilets}/edit`);
  await showStep(page, "Forhåndsvisning");
  const frame = page.frameLocator("iframe");
  await expect(frame.locator(".leaflet-container")).toBeVisible();

  await frame.getByRole("button", { name: "Zoom in" }).click();

  // The map sends its view after the zoom ends.
  await expect(async () => {
    await page.getByRole("button", { name: "Brug kortets visning" }).click();
    await expect(page.getByLabel("Zoom", { exact: true })).toHaveValue("15", {
      timeout: 500,
    });
  }).toPass();
  await expect(page.getByLabel("Længdegrad")).toHaveValue(/^\d+\.\d{5,6}$/);
  await expect(page.getByLabel("Breddegrad")).toHaveValue(/^\d+\.\d{5,6}$/);
});

test("updates the map and fills in the view when the view step opens", async ({
  page,
}) => {
  // The map has no view, so the fields start empty.
  await page.goto(`/builder/${maps["toilets-and-parking"]}/edit`);
  await page.getByLabel("Titel", { exact: true }).fill("Ny titel");
  await rendered(page);

  await showStep(page, "Forhåndsvisning");

  const frame = page.frameLocator(
    'iframe[title="Forhåndsvisning af \\"Ny titel\\""]',
  );
  await expect(frame.locator("h1")).toHaveText("Ny titel");
  await expect(page.getByLabel("Zoom", { exact: true })).toHaveValue("15");
  // The server renders the numbers again with 6 decimals.
  await expect(page.getByLabel("Længdegrad")).toHaveValue(/^10\.214320?$/);
  await expect(page.getByLabel("Breddegrad")).toHaveValue(/^56\.153550?$/);
  await expectNoAxeViolations(page);
});

test("opens and closes a layer, and opens it for an error", async ({
  page,
}) => {
  await page.goto(`/builder/${maps["toilets-and-parking"]}/edit`);
  await showStep(page, "Lag");

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
  await showStep(page, "Indstillinger");

  await page.getByRole("button", { name: "Gem og vis kortet" }).click();

  // The error is in a closed layer on another step, so the form shows the
  // step and opens the layer.
  await expect(label).toBeVisible();
  await expect(label).toHaveAttribute("aria-invalid", "true");
  await expect(page).toHaveURL(`/builder/${maps["toilets-and-parking"]}/edit`);
});
