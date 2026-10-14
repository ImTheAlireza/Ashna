// Render the current Ashna identity, never relabel old raster artwork.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
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
  const page = await browser.newPage({ viewport: { width: 1050, height: 520 }, deviceScaleFactor: 2, locale: 'fa-IR' });
  const failures = [];
  page.on('pageerror', e => failures.push(e.message));
  page.on('response', r => { if (r.status() >= 400) failures.push(r.url()); });
  await page.goto(`${process.env.ASHNA_GALLERY_URL || 'http://127.0.0.1:8000/product-images/gallery-design/'}cover.html`, { waitUntil: 'networkidle' });
  await page.evaluate(async () => { await document.fonts.ready; await Promise.all([...document.images].map(i => i.decode())); });
  for (const name of ['ashna-main.jpg','ashna-main-refined.jpg','ashna-main-branded.jpg']) {
    await page.screenshot({ path: path.join(root, 'product-images', name), type: 'jpeg', quality: 94 });
  }
  await page.setViewportSize({ width: 320, height: 320 });
  await page.screenshot({ path: path.join(root, 'product-images/ashna-thumbnail.png'), scale: 'css' });
  if (failures.length) throw new Error(failures.join('\n'));
  console.log('PASS: covers 2100 × 1040 and thumbnail 320 × 320 rendered with the final Ashna identity.');
} finally { await browser.close(); }
