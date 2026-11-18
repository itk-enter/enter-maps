/**
 * @file The test fixture of the browser tests. Import "test" and "expect"
 * from this file, not from "@playwright/test".
 *
 * Each page gets stubs for the services outside the site, so the tests do
 * not need Enter, Adressevælger or the tiles:
 * - /data/{dataSet}.geojson gives the GeoJSON in fixtures/.
 * - Adressevælger gives one address and one street.
 * - All other hosts give an empty answer.
 *
 * A test fails if the page has a JavaScript error.
 */

import { readFileSync } from "node:fs";
import { basename } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { test as base, expect } from "@playwright/test";

/**
 * The id of each map, from tests/Fixtures/map-config, with the name of the
 * file as key. setup.sh writes the imported maps to var/browser/maps.json,
 * with "app:map-config:import --format=json".
 *
 * @type {{toilets: string, "toilets-and-parking": string}}
 */
export const maps = Object.fromEntries(
  JSON.parse(
    readFileSync(new URL("../../var/browser/maps.json", import.meta.url)),
  ).map(({ file, id }) => [basename(file, ".json"), id]),
);

/**
 * The address that the Adressevælger stub gives, with its real position in
 * UTM zone 32N. It is near the places in the fixtures.
 */
export const ADDRESS = {
  id: "c1496b42-51c0-48ed-afc7-b340ac97ae13",
  titel: "Hack Kampmanns Plads 2, 8000 Aarhus C",
  x: 575398.21,
  y: 6223820.14,
};

/** The street that the Adressevælger stub gives. */
export const STREET = { id: "street-1", titel: "Hack Kampmanns Plads" };

/**
 * @param {string} name The file name in fixtures/.
 * @returns {object} The GeoJSON.
 */
function fixture(name) {
  return JSON.parse(
    readFileSync(new URL(`./fixtures/${name}`, import.meta.url)),
  );
}

const DATA_SETS = {
  "merged-public-toilets": fixture("toilets.geojson"),
  "merged-handicap-parking-spots": fixture("parking.geojson"),
};

/**
 * Stubs the services outside the site. Playwright uses the last route that
 * matches, so the route for all requests comes first.
 *
 * @param {import("@playwright/test").Page} page The page.
 * @param {string} baseURL The address of the site.
 */
async function stubServices(page, baseURL) {
  const site = new URL(baseURL).host;

  await page.route(
    (url) => url.host !== site,
    (route) => route.fulfill({ status: 204 }),
  );

  await page.route("**/data/*.geojson*", (route) => {
    const { pathname } = new URL(route.request().url());
    const dataSet = pathname.match(/\/data\/(.+)\.geojson$/)?.[1];

    return dataSet in DATA_SETS
      ? route.fulfill({ json: DATA_SETS[dataSet] })
      : route.fulfill({ status: 404 });
  });

  await page.route("https://adressevaelger.dk/**", (route) => {
    const { pathname } = new URL(route.request().url());

    if (pathname === "/husnumre/soeg") {
      return route.fulfill({
        json: {
          status: "ok",
          fund: [
            { type: "husnummer", id: ADDRESS.id, titel: ADDRESS.titel },
            { type: "navngivenvej", id: STREET.id, titel: STREET.titel },
          ],
        },
      });
    }

    return route.fulfill({
      json: {
        status: "ok",
        husnummer: {
          adgangspunkt: { koordinater: { x: ADDRESS.x, y: ADDRESS.y } },
        },
      },
    });
  });
}

export const test = base.extend({
  page: async ({ page, baseURL }, use) => {
    const errors = [];

    page.on("pageerror", (error) => errors.push(error.message));
    await stubServices(page, baseURL);

    await use(page);

    expect(errors, "JavaScript errors on the page").toEqual([]);
  },
});

export { expect };

/**
 * Runs axe-core on the page, and fails if it finds a violation. The message
 * gives the rule and the elements of each violation.
 *
 * @param {import("@playwright/test").Page} page The page.
 */
export async function expectNoAxeViolations(page) {
  const { violations } = await new AxeBuilder({ page }).analyze();

  expect(
    violations.map(
      (violation) =>
        `${violation.id}: ${violation.nodes.map((node) => node.target.join(" ")).join(", ")}`,
    ),
    "axe-core violations",
  ).toEqual([]);
}
