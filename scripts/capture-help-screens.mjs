// Capture the help illustrations from a disposable, Persian WordPress site.
// TEST ONLY: this installs a *fixture* ZIP on a local site. Never point it at a customer site.
// See docs/help/README.md for setup, fixture limitations, and the required environment variables.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, '.cache/package.json'));
const { chromium: playwright } = require('playwright-core');
const cm = require('@sparticuz/chromium');
const chromium = cm.default || cm;
const sharp = require('sharp');
const base = process.env.HELP_WP_URL || 'http://127.0.0.1:9400';
const site = new URL(base);
if (site.protocol !== 'http:' || site.hostname !== '127.0.0.1') {
  throw new Error('For safety, screenshots can only be captured from a disposable 127.0.0.1 site.');
}
const username = process.env.HELP_WP_USER;
const password = process.env.HELP_WP_PASSWORD;
if (!username || !password) throw new Error('Set HELP_WP_USER and HELP_WP_PASSWORD for the disposable site.');
const fixtureZip = path.resolve(root, process.env.HELP_FIXTURE_ZIP || '.cache/help-fixture/ashna.zip');
const allowedFixtureDir = path.resolve(root, '.cache/help-fixture') + path.sep;
if (!fixtureZip.startsWith(allowedFixtureDir)) throw new Error('The fixture ZIP must be under .cache/help-fixture/.');
const pagePath = process.env.HELP_LOGIN_PAGE || '/?page_id=7';
if (!pagePath.startsWith('/') || pagePath.startsWith('//')) throw new Error('HELP_LOGIN_PAGE must be a local URL path.');
const out = path.join(root, 'docs/help/img');
await fs.mkdir(out, { recursive: true });

// The browser renders at 2x; cropping keeps the real UI unchanged while increasing
// legibility when illustrations are physically smaller in the PDF.
const scale = 2;
async function savePngAsWebp(name, png, crop = null) {
  let image = sharp(png);
  const m = await image.metadata();
  if (crop) {
    const width = Math.min(m.width, Math.round(crop.width * scale));
    const left = crop.center ? Math.floor((m.width - width) / 2) : Math.round((crop.left || 0) * scale);
    const top = Math.round((crop.top || 0) * scale);
    const height = Math.min(m.height - top, Math.round(crop.height * scale));
    if (left < 0 || top < 0 || height <= 0 || left + width > m.width) throw new Error(`Invalid crop for ${name}`);
    image = image.extract({ left, top, width, height });
  }
  const target = path.join(out, `${name}.webp`);
  const info = await image.webp({ quality: 88, effort: 5 }).toFile(target);
  console.log(`${name}: ${info.width}×${info.height}, ${info.size} bytes`);
}
async function captureLocator(page, name, locator, maxHeight = null) {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  // Keep the sticky admin header above the subject instead of overlapping it.
  await page.evaluate(() => window.scrollTo(0, 0));
  const size = await locator.boundingBox();
  if (!size || size.width < 50 || size.height < 30) throw new Error(`Invalid element size for ${name}`);
  const png = await locator.screenshot({ animations: 'disabled', timeout: 60000 });
  const crop = maxHeight && size.height > maxHeight ? { width: size.width, height: maxHeight } : null;
  await savePngAsWebp(name, png, crop);
}
async function captureWithPadding(page, name, locator, pad = 12, maxHeight = null) {
  await page.evaluate(() => window.scrollTo(0, 0));
  const b = await locator.boundingBox();
  if (!b) throw new Error(`Missing element for ${name}`);
  const height = maxHeight ? Math.min(b.height + 2 * pad, maxHeight) : b.height + 2 * pad;
  const png = await page.screenshot({
    animations: 'disabled',
    clip: { x: Math.max(0, b.x - pad), y: Math.max(0, b.y - pad), width: b.width + 2 * pad, height },
  });
  await savePngAsWebp(name, png);
}
async function goto(page, url) {
  const response = await page.goto(base + url, { waitUntil: 'domcontentloaded', timeout: 120000 });
  if (!response || !response.ok()) throw new Error(`Could not open ${url}: ${response?.status()}`);
  await page.evaluate(() => document.fonts.ready);
}
async function selectTab(page, tab) {
  await page.locator(`.ashna-nav-item[data-tab="${tab}"]`).click();
  await page.locator(`#ashna-tab-${tab}.active`).waitFor({ state: 'visible' });
  await page.waitForTimeout(350);
}

const exe = process.env.CHROME_PATH || await chromium.executablePath();
const browser = await playwright.launch({ executablePath: exe, args: chromium.args, headless: true });
try {
  // A taller viewport prevents Playwright from scrolling subjects underneath
  // the plugin's sticky header when taking element screenshots.
  const context = await browser.newContext({ viewport: { width: 1440, height: 1800 }, deviceScaleFactor: scale, locale: 'fa-IR' });
  const page = await context.newPage();
  const pageErrors = [];
  page.on('pageerror', e => pageErrors.push(e.message));
  await goto(page, '/wp-login.php');
  if ((await page.locator('html').getAttribute('lang')) !== 'fa-IR' || (await page.locator('html').getAttribute('dir')) !== 'rtl') {
    throw new Error('WordPress is not configured as Persian RTL.');
  }
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForURL(/\/wp-admin\//, { timeout: 120000 }), page.locator('#wp-submit').click()]);

  if (process.env.HELP_CAPTURE_INSTALL !== '0') {
    // Abort rather than overwriting an existing plugin on the disposable site.
    await goto(page, '/wp-admin/plugins.php');
    if (await page.locator('tr[data-plugin="ashna/ashna.php"]').count()) {
      throw new Error('Ashna is already installed. Reset the disposable site before capturing installation.');
    }
    await goto(page, '/wp-admin/plugin-install.php?tab=upload');
    await page.locator('input[type="file"]').setInputFiles(fixtureZip);
    const upload = await page.locator('.upload-plugin').screenshot({ animations: 'disabled' });
    await savePngAsWebp('install-2-file', upload, { center: true, width: 880, top: 35, height: 190 });
    await Promise.all([
      page.waitForURL(/update\.php\?action=upload-plugin/, { timeout: 120000 }),
      page.locator('#install-plugin-submit').click(),
    ]);
    const result = page.locator('#wpbody-content .wrap');
    if (!(await result.innerText()).includes('افزونه با موفقیت نصب شد.')) {
      throw new Error(`Installation did not succeed: ${(await result.innerText()).slice(0, 700)}`);
    }
    await captureWithPadding(page, 'install-3-done', result, 8);
    await Promise.all([
      page.waitForURL(/plugins\.php\?activate=true/, { timeout: 120000 }),
      result.getByRole('link', { name: 'فعال‌کردن افزونه' }).click(),
    ]);
    const row = page.locator('tr[data-plugin="ashna/ashna.php"]');
    if (!(await row.innerText()).includes('تنظیمات')) throw new Error('The fixture plugin did not boot after WordPress activation.');
    await captureLocator(page, 'install-4-table', row.locator('td.plugin-title'));
  }

  await goto(page, '/wp-admin/admin.php?page=ashna-otp');
  await page.locator('.ashna-nav-item[data-tab="dashboard"]').waitFor({ state: 'visible', timeout: 30000 });
  const nav = await page.locator('.ashna-nav').boundingBox();
  if (!nav) throw new Error('Ashna navigation is missing.');
  await page.evaluate(() => window.scrollTo(0, 0));
  const screenshot = await page.screenshot({
    animations: 'disabled',
    clip: { x: Math.floor(nav.x), y: 150, width: Math.floor(1440 - nav.x), height: 620 },
  });
  await savePngAsWebp('setup-1-panel', screenshot);

  await selectTab(page, 'sms_gateways');
  await captureLocator(page, 'setup-2-gateway', page.locator('#ashna-tab-sms_gateways > .ashna-card').first(), 640);
  await selectTab(page, 'auth_flow');
  await captureLocator(page, 'setup-3-route', page.locator('#ashna-mobile-strategy-card'), 510);
  await selectTab(page, 'bale_email');
  await captureLocator(page, 'setup-4-bale', page.locator('#ashna-tab-bale_email .ashna-card').first(), 470);
  await selectTab(page, 'appearance_studio');
  await captureLocator(page, 'setup-5-style', page.locator('#ashna-tab-appearance_studio .ashna-studio-layout'), 545);
  await selectTab(page, 'social');
  await captureWithPadding(page, 'setup-6-google', page.locator('.ashna-sso-card[data-sso-provider="google"] .ashna-sso-config'), 8, 480);
  await selectTab(page, 'woocommerce');
  await captureLocator(page, 'setup-7-wc', page.locator('#ashna-tab-woocommerce .ashna-card').first(), 445);
  await selectTab(page, 'security_firewall');
  await captureLocator(page, 'setup-8-limits', page.locator('#ashna-rate-limit-card'));
  await selectTab(page, 'tools_backup');
  await captureLocator(page, 'setup-9-tester', page.locator('#ashna-tab-tools_backup > .ashna-card').first());
  await selectTab(page, 'dashboard');
  await captureLocator(page, 'setup-11-checklist', page.locator('.ashna-setup-checklist-card'));

  // A separate, unauthenticated browser context shows the real shortcode output.
  const visitor = await browser.newContext({ viewport: { width: 1200, height: 1000 }, deviceScaleFactor: scale, locale: 'fa-IR' });
  const publicPage = await visitor.newPage();
  await goto(publicPage, pagePath);
  await captureLocator(publicPage, 'setup-10-form', publicPage.locator('.ashna-otp-wrapper').first());
  if (pageErrors.length) throw new Error(`Browser script errors: ${pageErrors.join('; ')}`);
  // Closing a secondary context can terminate single-process Chromium; let
  // browser.close() in finally dispose of both contexts together.
  console.log('Captured screenshots from Persian WordPress. The license is a test fixture, not the vendor license.');
} finally {
  await browser.close();
}
