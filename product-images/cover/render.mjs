// Render the editable desktop cover without a running web server.
// Usage: node product-images/cover/render.mjs [scale] [output.png]
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
      throw new Error(`Missing ${name}. See product-images/cover/README-FA.md.`);
    }
  }
}

export async function openCover(scale = 1) {
  if (!Number.isFinite(scale) || scale <= 0) throw new Error('Scale must be a positive number.');
  const chromiumModule = loadPackage('@sparticuz/chromium');
  const chromium = chromiumModule.default ?? chromiumModule;
  const { chromium: playwright } = loadPackage('playwright-core');
  const browser = await playwright.launch({
    executablePath: process.env.CHROMIUM_PATH ?? await chromium.executablePath(),
    args: [...chromium.args, '--font-render-hinting=none', '--force-color-profile=srgb'],
    headless: true,
  });
  try {
    const page = await browser.newPage({
      viewport: { width: 2100, height: 1040 },
      deviceScaleFactor: scale,
    });
    await page.goto(pathToFileURL(path.join(here, 'index.html')).href, {
      waitUntil: 'load', timeout: 120000,
    });
    await page.evaluate(() => document.fonts.ready);
    return { browser, page };
  } catch (error) {
    await browser.close();
    throw error;
  }
}

export async function renderCover(scale = 1, output = path.join(tmpdir(), 'cover-1x.png')) {
  const { browser, page } = await openCover(scale);
  try {
    const broken = await page.locator('#cover img').evaluateAll(images =>
      images.filter(image => !image.complete || !image.naturalWidth).map(image => image.src),
    );
    if (broken.length) throw new Error(`Images failed to load: ${broken.join(', ')}`);
    const cover = page.locator('#cover');
    const box = await cover.boundingBox();
    if (!box || box.width !== 2100 || box.height !== 1040) {
      throw new Error('Cover must be 2100 × 1040 CSS pixels.');
    }
    await cover.screenshot({ path: output, type: 'png', scale: 'device', timeout: 120000 });
    console.log(`Saved ${output} (${2100 * scale} × ${1040 * scale})`);
  } finally {
    await browser.close();
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  await renderCover(Number(process.argv[2] ?? 1), process.argv[3]);
}
