import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';
const require = createRequire(process.env.ASHNA_RENDER_MODULES || import.meta.url);
const { chromium: playwright } = require('playwright-core');
const chromiumModule = require('@sparticuz/chromium');
const chromium = chromiumModule.default || chromiumModule;
if (process.env.ASHNA_BUNDLED_LIBS === '1') {
  const libs = await chromiumModule.inflate(path.resolve(path.dirname(require.resolve('@sparticuz/chromium')), '../bin/al2023.tar.br'));
  chromiumModule.setupLambdaEnvironment(path.join(libs, 'lib'));
}
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const output = path.join(root, '.cache/gallery-render');
await fs.mkdir(output, { recursive: true });
const base = process.env.ASHNA_GALLERY_URL || 'http://127.0.0.1:8000/product-images/gallery-design/';
const browser = await playwright.launch({ executablePath: process.env.CHROME_PATH || await chromium.executablePath(), args: chromium.args, headless: true });
const report = [];
try {
  const page = await browser.newPage({ viewport: { width: 800, height: 900 }, deviceScaleFactor: 2, locale: 'fa-IR' });
  const failures = [];
  page.on('pageerror', error => failures.push(error.message));
  page.on('response', response => { if (response.status() >= 400) failures.push(`${response.status()} ${response.url()}`); });
  for (let slide = 1; slide <= 8; slide++) {
    await page.goto(`${base}render.html?slide=${slide}`, { waitUntil: 'networkidle' });
    await page.evaluate(async () => {
      await document.fonts.ready;
      await Promise.all([...document.images].map(image => image.decode()));
    });
    const metrics = await page.evaluate(() => {
      const h1 = document.querySelector('h1');
      const css = getComputedStyle(h1);
      const rect = h1.getBoundingClientRect();
      return {
        file: document.querySelector('#poster').dataset.slide,
        title: h1.innerText,
        font: css.fontFamily, size: css.fontSize, weight: css.fontWeight,
        lineHeight: css.lineHeight, x: rect.x, y: rect.y, width: rect.width, height: rect.height,
        fontLoaded: document.fonts.check('900 43px Estedad'),
        clippedTitles: [...h1.children].some(e => e.scrollWidth > e.clientWidth),
        posterOverflow: document.documentElement.scrollWidth > 800 || document.documentElement.scrollHeight > 900
      };
    });
    if (!metrics.fontLoaded || metrics.clippedTitles || metrics.posterOverflow) throw new Error(`Layout validation failed: ${JSON.stringify(metrics)}`);
    if (report.length) for (const key of ['font','size','weight','lineHeight','x','y','width','height']) {
      if (metrics[key] !== report[0][key]) throw new Error(`Inconsistent title ${key} on slide ${slide}`);
    }
    await page.locator('#poster').screenshot({ path: path.join(output, `${metrics.file}.png`), animations: 'disabled' });
    report.push(metrics);
    console.log(`Rendered ${metrics.file}: ${metrics.font} ${metrics.weight} ${metrics.size}`);
  }
  if (failures.length) throw new Error(failures.join('\n'));
  await fs.writeFile(path.join(output, 'render-report.json'), JSON.stringify(report, null, 2));
  console.log('PASS: all eight titles have identical computed typography and bounding boxes; fonts and images loaded.');
} finally { await browser.close(); }
