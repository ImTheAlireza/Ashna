// Browser checks for the static product preview, NOT the plugin/authentication backend.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, 'product-images/gallery-design/package.json'));
const { chromium: playwright } = require('playwright-core');
const chromiumModule = require('@sparticuz/chromium');
const chromium = chromiumModule.default || chromiumModule;
if (process.env.ASHNA_BUNDLED_LIBS === '1') {
  const libs = await chromiumModule.inflate(path.resolve(path.dirname(require.resolve('@sparticuz/chromium')), '../bin/al2023.tar.br'));
  chromiumModule.setupLambdaEnvironment(path.join(libs, 'lib'));
}
const browser = await playwright.launch({ executablePath: process.env.CHROME_PATH || await chromium.executablePath(), args: chromium.args, headless: true });
const base = process.env.LANDING_URL || 'http://127.0.0.1:8080/';
const output = path.join(root, '.cache/landing-preview');
await fs.mkdir(output, { recursive: true });
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [], failures = [], resources = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failures.push(request.url()));
  page.on('request', request => resources.push({ url: request.url(), method: request.method() }));
  await page.clock.install();
  await page.goto(base);
  await page.evaluate(() => document.fonts.ready);
  assert.equal(await page.title(), 'آشنا — یک ورود ساده، یک شروع آشنا');
  for (const width of [320, 390, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 1000 });
    for (const theme of ['light', 'dark']) {
      await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${width}/${theme}: page overflow`);
    }
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.evaluate(() => document.documentElement.dataset.theme = 'light');
  for (const a of await page.locator('a[href^="#"]').all()) {
    const id = (await a.getAttribute('href')).slice(1);
    assert(await page.locator(`[id="${id}"]`).count(), `Broken anchor ${id}`);
  }
  const allLinks = await page.locator('a[href]:not([href^="#"])').evaluateAll(links => links.map(a => a.href));
  for (const link of allLinks) {
    assert.equal(new URL(link).origin, new URL(base).origin, `Unexpected outbound link: ${link}`);
    assert((await context.request.get(link)).ok(), `Missing linked asset ${link}`);
  }
  for (const tab of await page.locator('.settings-tabs [role=tab]').all()) {
    await tab.click();
    await page.locator('#settingsImage').evaluate(image => image.decode());
    assert.equal(await tab.getAttribute('aria-selected'), 'true');
    await page.locator('#zoomShot').click();
    assert(await page.locator('#imageDialog').evaluate(dialog => dialog.open));
    await page.locator('#dialogImage').evaluate(image => image.decode());
    await page.keyboard.press('Escape');
    assert(!(await page.locator('#imageDialog').evaluate(dialog => dialog.open)));
    assert.equal(await page.locator('#zoomShot').evaluate(el => el === document.activeElement), true);
  }
  await page.locator('#settings-routing').click();
  await page.locator('#requestCode').click();
  assert.equal(await page.locator('#demoPhone').getAttribute('aria-invalid'), 'true');
  await page.locator('#demoPhone').fill('۰۹۱۲۳۴۵۶۷۸۹');
  const requestCount = resources.length;
  await page.locator('#requestCode').click();
  assert(await page.locator('#verifyPanel').isVisible());
  assert(await page.locator('#resendCode').isDisabled());
  await page.locator('#demoCode').fill('000000');
  await page.locator('#verifyCode').click();
  assert.equal(await page.locator('#demoCode').getAttribute('aria-invalid'), 'true');
  await page.clock.fastForward(61000);
  assert(await page.locator('#resendCode').isEnabled());
  await page.locator('#resendCode').click();
  assert(await page.locator('#resendCode').isDisabled());
  await page.locator('#demoCode').fill('۱۲۳۴۵۶');
  await page.locator('#demoCode').press('Enter');
  assert(await page.locator('#successPanel').isVisible());
  await page.locator('#resetDemo').click();
  assert.equal(await page.locator('#demoPhone').inputValue(), '');
  await page.locator('#tab-email').click();
  await page.locator('#demoEmail').fill('invalid');
  await page.locator('#requestCode').click();
  assert.equal(await page.locator('#demoEmail').getAttribute('aria-invalid'), 'true');
  await page.locator('#demoEmail').fill('hello@example.com');
  await page.locator('#demoEmail').press('Enter');
  await page.locator('#demoCode').fill('123456');
  await page.locator('#verifyCode').click();
  assert(await page.locator('#successPanel').isVisible());
  await page.locator('#tab-passkey').click();
  assert(await page.locator('#panel-passkey').isVisible());
  assert(await page.locator('#requestCode').isHidden());
  assert.equal(resources.length, requestCount, 'Demo sent a network request');
  assert.equal(await page.evaluate(() => document.cookie), '');
  assert.equal(await page.evaluate(() => sessionStorage.length), 0);
  assert.equal(await page.evaluate(() => localStorage.length), 0);
  await page.locator('#tab-passkey').press('Home');
  assert.equal(await page.locator('#tab-sms').getAttribute('aria-selected'), 'true');
  await page.locator('#copyShortcode').click();
  await page.waitForFunction(() => document.getElementById('copyStatus').textContent.length > 0);
  await page.locator('#faq summary').first().click();
  assert(await page.locator('#faq details').first().getAttribute('open') !== null);
  await page.locator('#themeToggle').click();
  assert.equal(await page.evaluate(() => document.documentElement.dataset.theme), 'dark');
  await page.reload();
  assert.equal(await page.evaluate(() => document.documentElement.dataset.theme), 'dark');
  await page.locator('#themeToggle').click();
  await page.evaluate(() => scrollTo(0, 0));
  await page.screenshot({ path: path.join(output, 'desktop.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('#menuToggle').click();
  assert.equal(await page.locator('#menuToggle').getAttribute('aria-expanded'), 'true');
  await page.locator('#navLinks a').first().click();
  assert.equal(await page.locator('#menuToggle').getAttribute('aria-expanded'), 'false');
  await page.evaluate(() => scrollTo(0, 0));
  await page.screenshot({ path: path.join(output, 'mobile.png'), fullPage: true });
  await page.locator('#tab-email').click();
  await page.locator('#demoEmail').fill('demo@example.com');
  await page.locator('#requestCode').click();
  await page.locator('#demoCode').fill('123456');
  await page.locator('#verifyCode').click();
  assert(await page.locator('#successPanel').isVisible());
  await page.locator('#settings-security').click();
  await page.locator('#zoomShot').click();
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
  await page.keyboard.press('Escape');
  // Scroll all lazy images into view before checking decode/error state.
  await page.evaluate(async () => { await Promise.all([...document.images].filter(img => img.getAttribute('src')).map(img => { img.loading = 'eager'; return img.decode(); })); });
  assert.deepEqual(errors, [], 'Browser/CSP errors');
  assert.deepEqual(failures, [], 'Failed requests');
  assert(resources.every(item => item.method === 'GET' && new URL(item.url).origin === new URL(base).origin));
  // Keep the initial context alive: bundled single-process Chromium needs a live page.
  const noJS = await browser.newContext({ javaScriptEnabled: false });
  const fallback = await noJS.newPage();
  await fallback.goto(base);
  assert(await fallback.locator('#features').isVisible());
  assert(await fallback.locator('noscript').first().isVisible());
  // browser.close() below owns context cleanup in single-process Chromium.
  console.log('PASS: local assets/CSP, anchors, 320–1440px in both themes, mobile menu, image tabs/dialog, SMS/email validation, Persian digits, retry timer, reset, Passkey disclosure, keyboard tabs, no demo network/storage, no-JS content.');
  console.log('Screenshots: .cache/landing-preview/{desktop,mobile}.png');
} finally { await browser.close(); }
