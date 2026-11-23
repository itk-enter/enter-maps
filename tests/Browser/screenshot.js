/**
 * @file Takes screenshots of pages of the dev site, for the description of a
 * pull request. Run it with "task screenshot".
 *
 * Each argument is a path, or a name and a path with "=" between them:
 *
 *     node screenshot.js / embed-code=/builder/01M83W56C4MDEV25NYEK4H808F/embed
 *
 * The script saves two full-page images of each page, at the width of a
 * desktop and of a phone, in var/screenshots/. Without a name, the name comes
 * from the path: "/" gives "home", and "/builder" gives "builder".
 *
 * The script does not click or type. For a page after a click, write a
 * short script with the same steps as a browser test.
 */

import { mkdir } from "node:fs/promises";
import { chromium } from "@playwright/test";

const BASE_URL = process.env.BASE_URL ?? "http://nginx:8080";
const OUTPUT = "../../var/screenshots";
const WIDTHS = { wide: 1280, phone: 390 };

const pages = process.argv.slice(2).map((argument) => {
  const [name, path] = argument.includes("=")
    ? argument.split("=", 2)
    : [null, argument];
  const slug =
    path
      .replace(/[?#].*$/, "")
      .replace(/[^a-z0-9]+/gi, "-")
      .replace(/^-|-$/g, "")
      .toLowerCase() || "home";

  return { name: name || slug, path };
});

if (pages.length === 0) {
  console.error("Give one or more paths, for example: task screenshot -- /");
  process.exit(1);
}

await mkdir(OUTPUT, { recursive: true });
const browser = await chromium.launch();

try {
  for (const { name, path } of pages) {
    for (const [size, width] of Object.entries(WIDTHS)) {
      const page = await browser.newPage({ viewport: { width, height: 800 } });
      const response = await page.goto(new URL(path, BASE_URL).href, {
        waitUntil: "networkidle",
      });
      if (!response?.ok()) {
        console.error(`${path} gave the status ${response?.status()}.`);
        process.exitCode = 1;
        await page.close();
        break;
      }

      const file = `${OUTPUT}/${name}-${size}.png`;
      await page.screenshot({ path: file, fullPage: true });
      await page.close();
      console.log(`var/screenshots/${name}-${size}.png`);
    }
  }
} finally {
  await browser.close();
}
