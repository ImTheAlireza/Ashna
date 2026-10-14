// Actual landing HTML/CSS in Chromium; no external font or service dependency.
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
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
try {
  const page = await browser.newPage();
  await page.route('https://**/*', route => route.abort());
  await page.goto(pathToFileURL(path.join(root, 'parsna-landing/index.html')).href);
  for (const width of [320, 390, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const theme of ['dark', 'light']) {
      await page.evaluate(theme => document.documentElement.setAttribute('data-theme', theme), theme);
      await page.locator('#ashna-technical summary').click();
      const checks = await page.evaluate(() => {
        const section = document.querySelector('#ashna-technical');
        const cards = [...section.querySelectorAll('article')];
        return {
          cards: cards.length,
          withinViewport: cards.every(card => {
            const b = card.getBoundingClientRect();
            return b.x >= 0 && b.right <= window.innerWidth + 1;
          }),
          noOverflow: section.scrollWidth <= section.clientWidth + 1,
          caveatsOpen: section.querySelector('details').open,
          caveats: section.querySelector('details').textContent,
        };
      });
      assert.equal(checks.cards, 2);
      assert(checks.withinViewport && checks.noOverflow, `${width}/${theme}: technical section overflows`);
      assert(checks.caveatsOpen && checks.caveats.includes('اتمیک') && checks.caveats.includes('لیست سفید'));
      await page.locator('#ashna-technical summary').click();
    }
  }
  console.log('PASS: technical section and expandable caveats render without overflow at 320/390/1280px in both themes.');
} finally { await browser.close(); }
