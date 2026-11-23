import { execFileSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import assert from "node:assert/strict";
import test from "node:test";
import { chromium } from "playwright";

const backend = new URL("../../backend/", import.meta.url);
const origin = new URL(process.env.RENTGRID_E2E_URL ?? "http://localhost:3000").origin;
const contact = { name: "Browser Test Guest", email: "booking-recovery@example.test", phone: "+639175550100" };

function fixture(run, ...args) {
  return execFileSync(process.env.RENTGRID_E2E_PHP ?? "php", ["tests/Support/booking-browser-fixture.php", run, ...args], {
    cwd: backend,
    env: { ...process.env, APP_ENV: "testing" },
    encoding: "utf8",
  });
}

test("a lost confirmation is recoverable after reload before booking another vehicle", { timeout: 60_000 }, async () => {
  assert.ok(["localhost", "127.0.0.1", "[::1]"].includes(new URL(origin).hostname), "Use a local RentGrid test server.");
  const run = randomBytes(6).toString("hex");
  const { date, vehicles: [original, replacement] } = JSON.parse(fixture(run));
  let browser;

  try {
    browser = await chromium.launch({ channel: process.env.RENTGRID_E2E_BROWSER_CHANNEL });
    const page = await browser.newPage({ baseURL: origin });
    const dates = new URLSearchParams({ pickup_date: date, return_date: date });
    const search = `/?${dates}`;
    const reviewLink = (vehicle) => page.getByRole("link", { name: `Review rental for ${vehicle.display_label}` });
    await page.goto(search);
    await reviewLink(original).click();
    await page.getByLabel("Full name").fill(contact.name);
    await page.getByLabel("Email address").fill(contact.email);
    await page.getByLabel("Phone number").fill(contact.phone);

    let reference;
    await page.route("**/api/bookings", async (route) => {
      const saved = await route.fetch({ maxRedirects: 0 });
      assert.equal(saved.status(), 201);
      ({ reference } = await saved.json());
      await route.abort("connectionreset");
    }, { times: 1 });
    await page.getByRole("button", { name: "Confirm booking" }).click();
    await page.getByRole("button", { name: "Recover confirmation" }).waitFor();
    assert.match(reference, /^RG-[0-9A-Z]{26}$/);
    const storage = await page.evaluate(() => JSON.stringify(sessionStorage));
    for (const value of Object.values(contact)) assert.ok(!storage.includes(value));

    await page.getByRole("link", { name: "Back to vehicle search" }).click();
    await page.waitForURL(new URL(search, origin).href);
    await page.reload();
    await page.getByRole("link", { name: "Recover previous booking" }).waitFor();
    await reviewLink(replacement).click();
    const recover = page.getByRole("link", { name: "Recover previous booking" });
    await recover.waitFor();
    assert.equal(await page.getByRole("button", { name: "Confirm booking" }).count(), 0);
    assert.equal(await page.getByLabel("Email address").count(), 0);

    await recover.click();
    await page.waitForURL(`**/book/confirmation/${reference}`);
    await page.getByText(reference, { exact: true }).waitFor();
    const availability = await page.request.get(`/api/vehicles/availability?${dates}`);
    assert.ok(availability.ok());
    const { data } = await availability.json();
    assert.ok(data.some(({ id }) => id === replacement.id));
    assert.ok(!data.some(({ id }) => id === original.id));

    await page.goto(search);
    await reviewLink(replacement).click();
    await page.getByLabel("Full name").fill(contact.name);
    await page.getByLabel("Email address").fill(contact.email);
    await page.getByLabel("Phone number").fill(contact.phone);
    await page.route("**/api/bookings", (route) => route.abort("connectionreset"), { times: 1 });
    await page.getByRole("button", { name: "Confirm booking" }).click();
    await page.getByRole("button", { name: "Recover confirmation" }).waitFor();
    await page.reload();
    await page.getByRole("button", { name: "Retry booking" }).waitFor();
    assert.equal(await page.getByLabel("Email address").inputValue(), "");
    await page.getByRole("link", { name: "choose another vehicle" }).click();
    await page.waitForURL(new URL(search, origin).href);
    await reviewLink(replacement).click();
    await page.getByRole("button", { name: "Confirm booking" }).waitFor();
  } finally {
    try {
      await browser?.close();
    } finally {
      fixture(run, "clean");
    }
  }
});
