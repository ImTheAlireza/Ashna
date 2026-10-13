// npm ci --prefix product-images/gallery-design
// SIGNA_BUNDLED_LIBS=1 node scripts/render-help.mjs
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, 'product-images/gallery-design/package.json'));
const { chromium: playwright } = require('playwright-core');
const chromiumModule = require('@sparticuz/chromium');
const chromium = chromiumModule.default || chromiumModule;
if (process.env.SIGNA_BUNDLED_LIBS === '1') {
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
  const layout = await page.evaluate(() => [...document.querySelectorAll('.page')].map((p, i) => {
    const footer = p.querySelector('.footer').getBoundingClientRect();
    const contents = [...p.children].filter(e => !e.classList.contains('footer'));
    const bottom = Math.max(...contents.map(e => e.getBoundingClientRect().bottom));
    const horizontalOverflow = [...p.querySelectorAll('*')].filter(e => e.scrollWidth > e.clientWidth + 2 && getComputedStyle(e).display !== 'inline').map(e => e.tagName);
    return { page: i + 1, footerGap: footer.top - bottom, horizontalOverflow, fontLoaded: document.fonts.check('900 27pt Estedad') };
  }));
  console.log(JSON.stringify(layout, null, 2));
  if (errors.length || layout.length !== 4 || layout.some(p => p.footerGap < 12 || p.horizontalOverflow.length || !p.fontLoaded)) {
    throw new Error('Guide layout validation failed: ' + errors.join(', '));
  }
  await fs.mkdir(path.join(root, 'deliverables'), { recursive: true });
  await page.pdf({ path: path.join(root, 'deliverables/Signa-Help-FA.pdf'), preferCSSPageSize: true, printBackground: true, tagged: true, outline: true });
  console.log('PASS: 4 pages, fonts/images loaded, no content/footer collision.');
} finally { await browser.close(); }
