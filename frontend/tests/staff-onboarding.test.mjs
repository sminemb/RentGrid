import { execFileSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import assert from "node:assert/strict";
import test from "node:test";
import { chromium } from "playwright";

const backend = new URL("../../backend/", import.meta.url);
const origin = new URL(process.env.RENTGRID_E2E_URL ?? "http://localhost:3000").origin;

function fixture(run, ...args) {
  return execFileSync(process.env.RENTGRID_E2E_PHP ?? "php", ["tests/Support/staff-browser-fixture.php", run, ...args], {
    cwd: backend,
    env: { ...process.env, APP_ENV: "testing" },
    encoding: "utf8",
  });
}

async function signIn(page, { name, email, password }) {
  await page.goto("/staff/sign-in");
  await page.getByLabel("Email address").fill(email);
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL("/staff");
  await page.getByRole("region", { name: "Staff session" }).getByText(name, { exact: true }).waitFor();
}

test("a staff administrator can create an account that signs in independently", { timeout: 60_000 }, async () => {
  assert.ok(["localhost", "127.0.0.1", "[::1]"].includes(new URL(origin).hostname), "Use a local RentGrid test server.");
  const run = randomBytes(6).toString("hex");
  const { admin, staffEmail } = JSON.parse(fixture(run));
  const staff = { name: "Browser Test Staff Member", email: staffEmail, password: randomBytes(16).toString("hex") };
  let browser;

  try {
    browser = await chromium.launch({ channel: process.env.RENTGRID_E2E_BROWSER_CHANNEL });
    const adminContext = await browser.newContext({ baseURL: origin });
    const adminPage = await adminContext.newPage();
    await signIn(adminPage, admin);
    await adminPage.getByRole("link", { name: "Create staff account" }).click();
    await adminPage.waitForURL("/staff/accounts/new");
    await adminPage.getByLabel("Full name").fill(staff.name);
    await adminPage.getByLabel("Email address").fill(staff.email);
    await adminPage.getByLabel("Initial password").fill(staff.password);
    await adminPage.getByLabel("Confirm password").fill(staff.password);
    await adminPage.getByRole("button", { name: "Create staff account" }).click();
    await adminPage.getByRole("status").filter({ hasText: "Staff account created." }).waitFor();

    const staffContext = await browser.newContext({ baseURL: origin });
    const staffPage = await staffContext.newPage();
    await signIn(staffPage, staff);
    await staffPage.getByRole("link", { name: "Manage fleet" }).waitFor();
    assert.equal(await staffPage.getByRole("link", { name: "Create staff account" }).count(), 0);
  } finally {
    try {
      await browser?.close();
    } finally {
      fixture(run, "clean");
    }
  }
});
