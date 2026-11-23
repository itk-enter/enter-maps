/**
 * @file Browser tests of /embed/leaflet/{id}, with the fixture maps:
 * - "toilets": one layer, a zoom gate at zoom 15, a select filter, search
 *   and a report link.
 * - "toilets-and-parking": two layers, a toggle filter, no zoom gate, a
 *   spinner while the data loads, and a condition below each layer. The
 *   condition of the toilets is on at the start, so "Toilet ved Åen" is not
 *   on the map at first.
 *
 * Each test that changes the state of the page also runs axe-core.
 */

import {
  ADDRESS,
  STREET,
  expect,
  expectNoAxeViolations,
  maps,
  test,
} from "./support.js";

const toilets = `/embed/leaflet/${maps.toilets}`;
const SEARCH = { name: "Søg efter sted eller adresse" };
const toiletsAndParking = `/embed/leaflet/${maps["toilets-and-parking"]}`;

/**
 * @param {import("@playwright/test").Page} page The page.
 * @returns {import("@playwright/test").Locator} The suggestions of the
 *   address search.
 */
const suggestions = (page) =>
  page.getByRole("listbox", { name: "Forslag" }).getByRole("option");

/**
 * @param {import("@playwright/test").Page} page The page.
 * @returns {import("@playwright/test").Locator} The rows of the list.
 */
const rows = (page) => page.locator("#map-list").getByRole("listitem");

test.describe("the toilets map", () => {
  test("holds the places back until the user zooms in", async ({ page }) => {
    await page.goto(toilets);

    await expect(
      page.getByRole("heading", { name: "Se steder på kortet" }),
    ).toBeVisible();
    await expect(page.getByRole("progressbar")).toBeVisible();
    await expect(page.locator("#map-list")).toBeHidden();
    await expectNoAxeViolations(page);
  });

  test("lists the places in the view", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16`);

    await expect(rows(page)).toHaveCount(3);
    await expect(rows(page).first()).toContainText("Dokk1");
    // A place without an address shows the fallback.
    await expect(
      rows(page).filter({ hasText: "Toilet ved Åen" }),
    ).toContainText("Adresse ikke registreret");
    await expectNoAxeViolations(page);
  });

  test("opens the detail view and gives focus back to the row", async ({
    page,
  }) => {
    await page.goto(`${toilets}?zoom=16`);

    const row = rows(page).filter({ hasText: "Dokk1" }).getByRole("button");
    await row.click();

    const title = page.getByRole("heading", { level: 2, name: "Dokk1" });
    await expect(title).toBeFocused();
    await expect(page.getByText("Ved indgangen")).toBeVisible();
    await expect(page.getByText("Lørdag og søndag 10-16")).toBeVisible();
    await expect(
      page.getByRole("link", { name: "Ret eller tilføj oplysninger" }),
    ).toHaveAttribute(
      "href",
      "https://example.aarhus.dk/form?entity=urn%3Atoilet%3Adokk1&sources=urn%3Asource%3Aa%2Curn%3Asource%3Ab",
    );
    await expectNoAxeViolations(page);

    await page.keyboard.press("Escape");

    await expect(title).toBeHidden();
    await expect(
      rows(page).filter({ hasText: "Dokk1" }).getByRole("button"),
    ).toBeFocused();
  });

  test("filters the list with the select filter", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16`);

    await page.getByLabel("Kørestolsadgang").selectOption("limited");

    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page)).toContainText("Havnen");
  });

  test("searches in the list", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16`);

    await page.getByRole("combobox", SEARCH).fill("åen");

    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page)).toContainText("Toilet ved Åen");
  });

  test("finds an address and lists the nearest places", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16`);

    const search = page.getByRole("combobox", SEARCH);
    await search.pressSequentially("Hack Kamp");

    await expect(suggestions(page)).toHaveCount(2);
    await expect(search).toHaveAttribute("aria-expanded", "true");
    await expectNoAxeViolations(page);

    await search.press("ArrowDown");
    await expect(search).toHaveAttribute(
      "aria-activedescendant",
      /search-suggestions-0/,
    );
    await search.press("Enter");

    await expect(search).toHaveValue(ADDRESS.titel);
    await expect(
      page.getByRole("heading", {
        name: `Nærmeste steder fra ${ADDRESS.titel}`,
      }),
    ).toBeVisible();
    // Dokk1 is at the address, so it is first, with the distance.
    await expect(rows(page).first()).toContainText("Dokk1");
    await expect(rows(page).first()).toContainText(/\d+ m/);
    await expectNoAxeViolations(page);
  });

  test("puts a chosen street back in the search field", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16`);

    const search = page.getByRole("combobox", SEARCH);
    await search.pressSequentially("Hack Kamp");
    await suggestions(page)
      .filter({ hasText: "Vælg for at søge videre" })
      .click();

    await expect(search).toHaveValue(`${STREET.titel} `);
  });

  test("selects a place from the select option", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16&select=urn:toilet:havnen`);

    await expect(
      page.getByRole("heading", { level: 2, name: "Havnen" }),
    ).toBeVisible();
  });

  test("uses English with lang=en", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16&lang=en`);

    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect(page.getByRole("heading", { name: "Results" })).toBeVisible();
    await expectNoAxeViolations(page);
  });

  test("shows only the map with sidebar=off", async ({ page }) => {
    await page.goto(`${toilets}?zoom=16&sidebar=off`);

    await expect(page.getByRole("combobox", SEARCH)).toHaveCount(0);
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(3);
    await expectNoAxeViolations(page);
  });
});

test.describe("the toilets and parking map", () => {
  test("shows a spinner until the data is loaded", async ({ page }) => {
    // Holds the data back until the test has seen the spinner.
    let release;
    const held = new Promise((resolve) => {
      release = resolve;
    });
    await page.route("**/data/*.geojson*", async (route) => {
      await held;
      await route.fallback();
    });
    await page.goto(toiletsAndParking);

    const spinner = page.getByRole("status").filter({
      hasText: "Henter stederne",
    });
    await expect(spinner).toBeVisible();
    await expectNoAxeViolations(page);

    release();
    await expect(spinner).toHaveCount(0);
    await expect(rows(page)).toHaveCount(4);
  });

  test("lists both layers, also a polygon", async ({ page }) => {
    await page.goto(toiletsAndParking);

    await expect(rows(page)).toHaveCount(4);
    await expect(rows(page).filter({ hasText: "Nørre Allé 1" })).toContainText(
      "Pladser: 2",
    );
    await expectNoAxeViolations(page);
  });

  test("turns a layer off", async ({ page }) => {
    await page.goto(toiletsAndParking);

    await page
      .getByRole("checkbox", { name: "Offentlige toiletter" })
      .uncheck();

    await expect(rows(page)).toHaveCount(2);
    await expect(rows(page).filter({ hasText: "Dokk1" })).toHaveCount(0);
  });

  test("turns the conditions below each layer on and off", async ({ page }) => {
    await page.goto(toiletsAndParking);

    const toilets = page.getByRole("group", {
      name: "Filtre for Offentlige toiletter",
    });
    const wheelchair = toilets.getByRole("checkbox", {
      name: "Kørestolsvenligt",
    });
    const spaces = page
      .getByRole("group", { name: "Filtre for Handicapparkering" })
      .getByRole("checkbox", { name: "Mindst 2 pladser" });
    await expect(wheelchair).toBeChecked();
    await expect(spaces).not.toBeChecked();
    await expect(rows(page).filter({ hasText: "Toilet ved Åen" })).toHaveCount(
      0,
    );
    await expectNoAxeViolations(page);

    await wheelchair.uncheck();
    await expect(rows(page)).toHaveCount(5);

    // Skolegade 5 has 1 space.
    await spaces.check();
    await expect(rows(page)).toHaveCount(4);
    await expect(rows(page).filter({ hasText: "Skolegade 5" })).toHaveCount(0);
  });

  test("keeps only the free places with the toggle filter", async ({
    page,
  }) => {
    await page.goto(toiletsAndParking);

    await page.getByRole("checkbox", { name: "Kun gratis" }).check();

    // Dokk1, Havnen and Nørre Allé 1 are free.
    await expect(rows(page)).toHaveCount(3);
    await expect(rows(page).filter({ hasText: "Skolegade 5" })).toHaveCount(0);
  });
});

test("tells that a map does not exist", async ({ page }) => {
  const response = await page.goto("/embed/leaflet/01ARZ3NDEKTSV4RRFFQ69G5FAV");

  expect(response.status()).toBe(404);
  await expect(
    page.getByRole("heading", { name: "Kortet er ikke tilgængeligt" }),
  ).toBeVisible();
  await expectNoAxeViolations(page);
});
