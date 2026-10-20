// Final export: CDP clip slices in page coordinates (no scrolling, no texture-merge artifacts).
import fs from 'node:fs';
process.env.LD_LIBRARY_PATH = '/tmp/alx';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');

const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args, '--font-render-hinting=none', '--force-color-profile=srgb'],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx' },
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 1000, height: 1200 }, deviceScaleFactor: 1, colorScheme: 'light' });
await page.goto('http://127.0.0.1:8124/product-images/infographic/index.html', { waitUntil: 'networkidle', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(1500);
await page.evaluate(() => window.scrollTo(0, 0));
await page.waitForTimeout(300);

const box = await page.locator('#poster').boundingBox();
const scrollY = await page.evaluate(() => window.scrollY);
const total = Math.ceil(box.height);
console.log('poster', box.width, total, 'scrollY', scrollY);

const client = await page.context().newCDPSession(page);
const step = 1000;
const dir = '/tmp/slices';
fs.rmSync(dir, { recursive: true, force: true });
fs.mkdirSync(dir, { recursive: true });

let i = 0;
for (let y = 0; y < total; y += step) {
  const h = Math.min(step, total - y);
  const res = await client.send('Page.captureScreenshot', {
    format: 'png',
    captureBeyondViewport: true,
    clip: { x: box.x, y: box.y + scrollY + y, width: 1000, height: h, scale: 2 },
  });
  fs.writeFileSync(`${dir}/s${String(i).padStart(2, '0')}.png`, Buffer.from(res.data, 'base64'));
  i++;
}
console.log('slices:', i);
await browser.close();
