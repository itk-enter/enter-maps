import { defineConfig, devices } from "@playwright/test";
import { STORAGE_STATE } from "./global-setup.js";

/**
 * @file The configuration of the browser tests. "task test:browser" runs
 * them in the playwright container against the dev site.
 * The report and the traces go to var/browser/. Each test starts with the
 * login of the global setup.
 */
export default defineConfig({
  testDir: ".",
  globalSetup: "./global-setup.js",
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: [
    ["list"],
    ["html", { open: "never", outputFolder: "../../var/browser/report" }],
  ],
  outputDir: "../../var/browser/results",
  use: {
    baseURL: process.env.BASE_URL ?? "http://nginx:8080",
    storageState: STORAGE_STATE,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
