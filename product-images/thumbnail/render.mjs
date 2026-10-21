// Renders the 320x320 product icon at 4x for a crisp master, then the caller downsamples.
process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const fs = await import('node:fs');

const scale = Number(process.argv[2] || 4);
const out = process.argv[3] || '/tmp/icon-4x.png';

const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args, '--font-render-hinting=none', '--force-color-profile=srgb'],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' },
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 520, height: 520 }, deviceScaleFactor: scale });
await page.goto('http://127.0.0.1:8124/product-images/thumbnail/index.html', { waitUntil: 'networkidle', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(700);
const buf = await page.locator('#icon').screenshot({ scale: 'device' });
fs.writeFileSync(out, buf);
console.log(out, buf.length);
await browser.close();
