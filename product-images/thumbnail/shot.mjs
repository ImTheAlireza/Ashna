// Quick 1x screenshot of the icon page for eyeballing changes.
process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' },
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 420, height: 420 } });
await page.goto('http://127.0.0.1:8124/product-images/thumbnail/index.html', { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(400);
const buf = await page.locator('#icon').screenshot();
const fs = await import('node:fs');
fs.writeFileSync(process.argv[2] || '/tmp/icon.png', buf);
console.log('ok', buf.length);
await browser.close();
