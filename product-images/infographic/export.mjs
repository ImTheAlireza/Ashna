// Render the editable poster in 2× slices, without a running web server.
// Run from anywhere: node product-images/infographic/export.mjs [optional-page-url]
import fs from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const localRequire = createRequire(import.meta.url);
const cacheRequire = createRequire(path.join(root, '.cache/browser/package.json'));

function loadPackage(name) {
  try {
    return localRequire(name);
  } catch (error) {
    if (error.code !== 'MODULE_NOT_FOUND') throw error;
    try {
      return cacheRequire(name);
    } catch {
      throw new Error(`Missing ${name}. See product-images/infographic/README-FA.md for installation.`);
    }
  }
}

const chromiumModule = loadPackage('@sparticuz/chromium');
const chromium = chromiumModule.default ?? chromiumModule;
const { chromium: playwright } = loadPackage('playwright-core');
const source = process.argv[2] ?? pathToFileURL(path.join(here, 'index.html')).href;
const slicesDir = process.env.ASHNA_SLICES_DIR ?? path.join(tmpdir(), 'ashna-infographic-slices');

const browser = await playwright.launch({
  executablePath: process.env.CHROMIUM_PATH ?? await chromium.executablePath(),
  args: [...chromium.args, '--font-render-hinting=none', '--force-color-profile=srgb'],
  headless: true,
});
try {
  const page = await browser.newPage({
    viewport: { width: 1000, height: 1200 },
    deviceScaleFactor: 1,
    colorScheme: 'light',
  });
  await page.goto(source, { waitUntil: 'load', timeout: 120000 });
  await page.evaluate(() => document.fonts.ready);
  const brokenImages = await page.locator('#poster img').evaluateAll(images =>
    images.filter(image => !image.complete || !image.naturalWidth).map(image => image.src),
  );
  if (brokenImages.length) throw new Error(`Images failed to load: ${brokenImages.join(', ')}`);

  const box = await page.locator('#poster').boundingBox();
  if (!box || Math.round(box.width) !== 1000) throw new Error('Poster must be 1000 CSS pixels wide.');
  const total = Math.ceil(box.height);
  const client = await page.context().newCDPSession(page);
  await fs.mkdir(slicesDir, { recursive: true });
  for (const name of await fs.readdir(slicesDir)) {
    if (/^s\d{3}\.png$/.test(name)) await fs.unlink(path.join(slicesDir, name));
  }

  const step = 1000;
  let count = 0;
  for (let y = 0; y < total; y += step) {
    const result = await client.send('Page.captureScreenshot', {
      format: 'png',
      captureBeyondViewport: true,
      clip: { x: box.x, y: box.y + y, width: 1000, height: Math.min(step, total - y), scale: 2 },
    });
    const name = `s${String(count).padStart(3, '0')}.png`;
    await fs.writeFile(path.join(slicesDir, name), Buffer.from(result.data, 'base64'));
    count += 1;
  }
  console.log(`Poster: 1000 × ${total} CSS pixels; ${count} 2× slices in ${slicesDir}`);
  console.log('Next: python3 product-images/infographic/stitch.py 90');
} finally {
  await browser.close();
}
