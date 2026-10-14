// npm ci --prefix product-images/gallery-design
// ASHNA_BUNDLED_LIBS=1 node scripts/render-help.mjs
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';
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
  const page = await browser.newPage({ viewport: { width: 900, height: 1200 }, locale: 'fa-IR' });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('requestfailed', r => errors.push(r.url()));
  await page.goto(pathToFileURL(path.join(root, 'docs/guide-fa.html')).href, { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all([...document.images].map(image => image.decode()));
  });
  const numeralCheck = await page.evaluate(() => {
    const text = document.body.innerText;
    const forbiddenDigits = text.match(/[0-9\u0660-\u0669]/gu) || [];
    const stepsValid = [...document.querySelectorAll('ol')].every(list =>
      [...list.querySelectorAll('.step-number')].every((step, i) =>
        step.textContent === String(i + 1).replace(/[0-9]/g, n => '۰۱۲۳۴۵۶۷۸۹'[Number(n)])));
    return { forbiddenDigits, stepsValid, phpMinimumPresent: text.includes('۸.۲') };
  });
  if (numeralCheck.forbiddenDigits.length || !numeralCheck.stepsValid || !numeralCheck.phpMinimumPresent) {
    throw new Error('Persian numeral validation failed: ' + JSON.stringify(numeralCheck));
  }
  const guideCheck = await page.evaluate(() => ({ installationFirst: document.querySelector('.page').innerText.includes('مراحل نصب'), screenshots: [...document.querySelectorAll('figure img')].map(img => ({ loaded: img.complete && img.naturalWidth >= 1800, alt: img.alt })), captions: [...document.querySelectorAll('figcaption')].every(el => el.textContent.includes('آفلاین')) }));
  if (!guideCheck.installationFirst || guideCheck.screenshots.length !== 5 || guideCheck.screenshots.some(img => !img.loaded || !img.alt) || !guideCheck.captions) throw new Error('Guide screenshot/order check failed');
  const layout = await page.evaluate(() => [...document.querySelectorAll('.page')].map((p, i) => {
    const footer = p.querySelector('.footer').getBoundingClientRect();
    const contents = [...p.children].filter(e => !e.classList.contains('footer'));
    const bottom = Math.max(...contents.map(e => e.getBoundingClientRect().bottom));
    const horizontalOverflow = [...p.querySelectorAll('*')].filter(e => e.scrollWidth > e.clientWidth + 2 && getComputedStyle(e).display !== 'inline').map(e => e.tagName);
    return { page: i + 1, footerGap: footer.top - bottom, horizontalOverflow, fontLoaded: document.fonts.check('900 27pt Estedad') };
  }));
  console.log(JSON.stringify(layout, null, 2));
  if (errors.length || layout.length !== 8 || layout.some(p => p.footerGap < 12 || p.horizontalOverflow.length || !p.fontLoaded)) {
    throw new Error('Guide layout validation failed: ' + errors.join(', '));
  }
  await fs.mkdir(path.join(root, 'deliverables'), { recursive: true });
  await page.pdf({ path: path.join(root, 'deliverables/Ashna-Help-FA.pdf'), preferCSSPageSize: true, printBackground: true, tagged: true, outline: true });
  console.log('PASS: 8 pages, installation first, five actual settings captures, Persian numerals, fonts/images and footer clearance verified.');
} finally { await browser.close(); }
