/**
 * @file Browser tests of the login with the mock OpenID Connect provider of
 * docker-compose.oidc.yml. The other tests start with the login of the
 * global setup. These tests start without a login, so they do not change
 * that session.
 */

import { expect, expectNoAxeViolations, test } from "./support.js";

test("shows the user who is logged in", async ({ page }) => {
  await page.goto("/builder");

  const header = page.getByRole("banner");
  await expect(header).toContainText("Medarbejder");
  await expect(header.getByRole("link", { name: "Log ud" })).toBeVisible();
});

test.describe("without a login", () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test("does not let a user in who is not in the group", async ({ page }) => {
    // The login page of the provider is on another host, which the stubs of support.js block.
    await page.unrouteAll();
    await page.route("https://cdn.jsdelivr.net/**", (route) =>
      route.fulfill({ status: 204 }),
    );

    await page.goto("/builder");
    await page.getByRole("button", { name: "Authorize as colleague" }).click();

    await expect(
      page.getByRole("heading", { level: 1, name: "Ingen adgang" }),
    ).toBeVisible();
    await expect(page.getByRole("banner")).toContainText("Kollega");
    await expectNoAxeViolations(page);
  });
});
